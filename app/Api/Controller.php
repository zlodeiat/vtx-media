<?php
namespace VTX\Media\Api;
use VTX\Media\Permissions\Policy;
use VTX\Media\Folders\FolderRepository;
use VTX\Media\Media\MediaRepository;
use VTX\Media\Media\FileFacts;
defined("ABSPATH") || exit();
final class Controller
{
    private $policy;
    private $folders;
    private $media;
    private $facts;
    public function __construct(
        Policy $policy,
        FolderRepository $folders,
        MediaRepository $media,
        FileFacts $facts
    ) {
        $this->policy = $policy;
        $this->folders = $folders;
        $this->media = $media;
        $this->facts = $facts;
    }
    public function permission(\WP_REST_Request $request)
    {
        return (new RouteRegistrar($this->policy))->permission($request);
    }
    private function route(
        string $path,
        string $method,
        callable $callback,
        array $args = []
    ): void {
        (new RouteRegistrar($this->policy))->register(
            $path,
            $method,
            $callback,
            $args,
        );
    }
    public function register(): void
    {
        $id = ["type" => "integer", "minimum" => 1, "required" => true];
        $zero = ["type" => "integer", "minimum" => 0, "default" => 0];
        $page = [
            "type" => "integer",
            "minimum" => 1,
            "maximum" => 1000000,
            "default" => 1,
        ];
        $text = [
            "type" => "string",
            "maxLength" => 191,
            "sanitize_callback" => "sanitize_text_field",
        ];
        $search = $text + ["default" => ""];
        $ids = [
            "type" => "array",
            "items" => ["type" => "integer", "minimum" => 1],
            "minItems" => 1,
            "maxItems" => 100,
            "uniqueItems" => true,
            "required" => true,
        ];
        $this->route(
            "/media",
            "GET",
            function ($r) {
                return $this->media->listing($r->get_params());
            },
            [
                "collection" => [
                    "type" => "string",
                    "maxLength" => 80,
                    "default" => "",
                ],
                "page" => $page,
                "per_page" => [
                    "type" => "integer",
                    "minimum" => 1,
                    "maximum" => 100,
                    "default" => 48,
                ],
                "search" => $search,
                "folder" => $zero,
                "author" => $zero,
                "type" => [
                    "type" => "string",
                    "enum" => ["all", "image", "video", "audio", "document"],
                    "default" => "all",
                ],
                "view" => [
                    "type" => "string",
                    "enum" => ["all", "favorites", "unorganized", "recent"],
                    "default" => "all",
                ],
                "sort" => [
                    "type" => "string",
                    "enum" => [
                        "newest",
                        "oldest",
                        "name_asc",
                        "name_desc",
                        "largest",
                        "smallest",
                    ],
                    "default" => "newest",
                ],
                "date" => [
                    "type" => "string",
                    "default" => "",
                    "validate_callback" => static function ($value) {
                        return is_string($value) &&
                            ($value === "" ||
                                (preg_match(
                                    '/^\d{4}-(0[1-9]|1[0-2])$/',
                                    $value,
                                ) &&
                                    (int) substr($value, 0, 4) >= 1000));
                    },
                ],
            ],
        );
        $this->route(
            "/media/(?P<id>\d+)",
            "GET",
            function ($r) {
                return $this->media->detail((int) $r["id"]);
            },
            ["id" => $id],
        );
        $this->route(
            "/media/(?P<id>\d+)",
            "PATCH",
            function ($r) {
                $body = $r->get_json_params();
                if (
                    !is_array($body) ||
                    !array_intersect_key(
                        $body,
                        array_flip(["title", "alt", "caption", "description"]),
                    )
                ) {
                    return new \WP_Error(
                        "vtx_empty",
                        __("Provide metadata to update.", "vtx-media"),
                        ["status" => 400],
                    );
                }
                return $this->media->update(
                    (int) $r["id"],
                    array_intersect_key(
                        $body,
                        array_flip(["title", "alt", "caption", "description"]),
                    ),
                );
            },
            [
                "id" => $id,
                "title" => ["type" => "string", "maxLength" => 1000],
                "alt" => ["type" => "string", "maxLength" => 5000],
                "caption" => ["type" => "string", "maxLength" => 50000],
                "description" => ["type" => "string", "maxLength" => 100000],
            ],
        );
        $this->route(
            "/move",
            "POST",
            function ($r) {
                return $this->media->move($r["ids"], (int) $r["folder_id"]);
            },
            ["ids" => $ids, "folder_id" => $zero],
        );
        $this->route(
            "/favorites",
            "POST",
            function ($r) {
                return $this->media->favorite($r["ids"], $r["favorite"]);
            },
            [
                "ids" => $ids,
                "favorite" => ["type" => "boolean", "required" => true],
            ],
        );
        $this->route(
            "/folders",
            "GET",
            function ($r) {
                return $this->folders->listing($r->get_params());
            },
            ["parent" => $zero, "page" => $page, "search" => $search],
        );
        $this->route(
            "/folders",
            "POST",
            function ($r) {
                return $this->folders->save(0, $r->get_params());
            },
            [
                "name" => $text + ["required" => true, "minLength" => 1],
                "parent_id" => $zero,
                "position" => [
                    "type" => "integer",
                    "minimum" => 0,
                    "maximum" => 1000000,
                    "default" => 0,
                ],
            ],
        );
        $this->route(
            "/folders/(?P<id>\d+)",
            "PATCH",
            function ($r) {
                return $this->folders->save(
                    (int) $r["id"],
                    array_intersect_key(
                        $r->get_params(),
                        array_flip(["name", "parent_id", "position"]),
                    ),
                );
            },
            [
                "id" => $id,
                "name" => $text + ["minLength" => 1],
                "parent_id" => ["type" => "integer", "minimum" => 0],
                "position" => [
                    "type" => "integer",
                    "minimum" => 0,
                    "maximum" => 1000000,
                ],
            ],
        );
        $this->route(
            "/folders/(?P<id>\d+)",
            "GET",
            function ($r) {
                return $this->folders->get((int) $r["id"]) ?:
                    new \WP_Error(
                        "vtx_folder",
                        __("Folder not found.", "vtx-media"),
                        ["status" => 404],
                    );
            },
            ["id" => $id],
        );
        $this->route(
            "/folders/(?P<id>\d+)",
            "DELETE",
            function ($r) {
                return $this->folders->delete((int) $r["id"]);
            },
            ["id" => $id],
        );
        $this->route("/size-index", "POST", function () {
            return $this->facts->batch($this->policy);
        });
        $this->route(
            "/authors",
            "GET",
            function ($r) {
                return $this->media->authors($r["search"]);
            },
            ["search" => $search],
        );
    }
}
