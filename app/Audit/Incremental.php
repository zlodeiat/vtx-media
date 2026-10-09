<?php
namespace VTX\Media\Audit;
use VTX\Media\Database\Schema;
defined("ABSPATH") || exit();
final class Incremental
{
    private $engine;
    private $dirty = [];
    private $deleted = [];
    public function __construct(AuditEngine $engine)
    {
        $this->engine = $engine;
    }
    public function hooks(): void
    {
        add_action("add_attachment", [$this, "queue"]);
        add_action("edit_attachment", [$this, "queue"]);
        foreach (
            ["added_post_meta", "updated_post_meta", "deleted_post_meta"]
            as $hook
        ) {
            add_action($hook, [$this, "meta"], 20, 3);
        }
        add_action("vtx_media/metadata_updated", [$this, "refresh"], 20);
        add_action("shutdown", [$this, "flush"]);
        add_action("delete_attachment", [$this, "deleted"]);
    }
    public function queue(int $id): void
    {
        if (isset($this->deleted[$id])) {
            return;
        }
        $this->engine->queue($id);
        $this->dirty[$id] = true;
    }
    public function meta($metaId, int $id, string $key): void
    {
        if (
            !in_array(
                $key,
                [
                    "_wp_attachment_image_alt",
                    "_wp_attached_file",
                    "_wp_attachment_metadata",
                    "_vtx_media_decorative",
                ],
                true,
            ) ||
            get_post_type($id) !== "attachment"
        ) {
            return;
        }
        if (
            $key === "_wp_attachment_image_alt" &&
            trim((string) get_post_meta($id, $key, true)) !== ""
        ) {
            delete_post_meta($id, "_vtx_media_decorative");
        }
        $this->queue($id);
    }
    public function refresh(int $id): void
    {
        $this->engine->audit($id);
        unset($this->dirty[$id]);
    }
    public function flush(): void
    {
        $ids = array_slice(array_keys($this->dirty), 0, 10);
        $this->engine->prepareBatch($ids);
        foreach ($ids as $id) {
            $this->engine->audit((int) $id);
            unset($this->dirty[$id]);
        }
    }
    public function background(): void
    {
        global $wpdb;
        $h = Schema::table("health");
        $ids = $wpdb->get_col(
            "SELECT h.attachment_id FROM $h h JOIN {$wpdb->posts} p ON p.ID=h.attachment_id WHERE h.state='pending' AND p.post_type='attachment' AND p.post_status='inherit' ORDER BY h.attachment_id LIMIT 25",
        );
        $this->engine->prepareBatch($ids);
        foreach ($ids as $id) {
            $this->engine->audit((int) $id);
        }
    }
    public function deleted(int $id): void
    {
        global $wpdb;
        unset($this->dirty[$id]);
        $this->deleted[$id] = true;
        foreach (["health", "findings"] as $table) {
            $wpdb->delete(Schema::table($table), ["attachment_id" => $id]);
        }
    }
}
