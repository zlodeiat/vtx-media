<?php
namespace VTX\Media\Api;
use VTX\Media\Permissions\Policy;
use VTX\Media\Entitlements\FeatureEntitlementInterface;
defined("ABSPATH") || exit();
/** Shared authentication, schemas and error envelope for Core and extensions. */
final class RouteRegistrar
{
    public const NAMESPACE = "vtx-media/v1";
    private $policy;
    private $entitlements;
    public function __construct(
        Policy $policy,
        ?FeatureEntitlementInterface $entitlements = null
    ) {
        $this->policy = $policy;
        $this->entitlements = $entitlements;
    }
    public function permission(\WP_REST_Request $request)
    {
        if (!$this->policy->access()) {
            return new \WP_Error(
                "vtx_forbidden",
                __("You cannot access this media library.", "vtx-media"),
                ["status" => rest_authorization_required_code()],
            );
        }
        if (
            !in_array($request->get_method(), ["GET", "HEAD"], true) &&
            !wp_verify_nonce($request->get_header("X-WP-Nonce"), "wp_rest")
        ) {
            return new \WP_Error(
                "vtx_nonce",
                __(
                    "Your session expired. Refresh the page and try again.",
                    "vtx-media",
                ),
                ["status" => 403],
            );
        }
        return true;
    }
    public function register(
        string $path,
        string $method,
        callable $callback,
        array $args = [],
        ?callable $permission = null,
        ?string $feature = null
    ): void {
        foreach ($args as &$schema) {
            $schema += [
                "validate_callback" => "rest_validate_request_arg",
                "sanitize_callback" => "rest_sanitize_request_arg",
            ];
        }
        unset($schema);
        register_rest_route(self::NAMESPACE, $path, [
            "methods" => $method,
            "args" => $args,
            "permission_callback" => function ($r) use ($permission, $feature) {
                $access = $this->permission($r);
                if (is_wp_error($access)) {
                    return $access;
                }
                if (
                    $feature &&
                    (!$this->entitlements ||
                        !$this->entitlements->hasFeature($feature))
                ) {
                    return new \WP_Error(
                        "vtx_entitlement",
                        __("This feature is not available.", "vtx-media"),
                        ["status" => 403],
                    );
                }
                $extra = apply_filters("vtx_media/route_permission", true, $r);
                if ($extra !== true) {
                    return $extra;
                }
                return $permission ? $permission($r) : true;
            },
            "callback" => static function ($r) use ($callback) {
                try {
                    return rest_ensure_response($callback($r));
                } catch (\Throwable $e) {
                    return new \WP_Error(
                        "vtx_failed",
                        __(
                            "The request could not be completed. Please try again.",
                            "vtx-media",
                        ),
                        ["status" => 500],
                    );
                }
            },
        ]);
    }
}
