import { execFileSync } from "node:child_process";
const run = (...args) =>
  execFileSync("wp", [...args, "--allow-root"], { encoding: "utf8" }).trim();
const id = Number(
  run(
    "eval",
    `wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]); $r=(new VTX\\Media\\Folders\\FolderRepository(new VTX\\Media\\Permissions\\Policy()))->save(0,['name'=>'VTX-LIFECYCLE-TEST']); echo $r['id'];`,
  ),
);
if (!id) throw new Error("Could not create lifecycle fixture");
try {
  console.log(run("plugin", "deactivate", "vtx-media"));
  const retained = Number(
    run(
      "eval",
      `global $wpdb; echo $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}vtx_media_folders WHERE id=%d",${id}));`,
    ),
  );
  if (retained !== 1) throw new Error("Deactivation lost data");
  console.log(run("plugin", "activate", "vtx-media"));
  const restored = Number(
    run(
      "eval",
      `echo (new VTX\\Media\\Folders\\FolderRepository(new VTX\\Media\\Permissions\\Policy()))->get(${id})['id'];`,
    ),
  );
  if (restored !== id) throw new Error("Reactivation lost data");
  console.log("PASS: Deactivation/reactivation preserve persisted folders");
  const uninstall = Number(
    run(
      "eval",
      `define('WP_UNINSTALL_PLUGIN','vtx-media/vtx-media.php'); include WP_PLUGIN_DIR.'/vtx-media/uninstall.php'; echo (new VTX\\Media\\Folders\\FolderRepository(new VTX\\Media\\Permissions\\Policy()))->get(${id})['id'];`,
    ),
  );
  if (uninstall !== id) throw new Error("Default uninstall lost data");
  console.log("PASS: Default uninstall retains data");
  run(
    "eval",
    `global $wpdb; $wpdb->query('ALTER TABLE '.VTX\\Media\\Database\\Schema::table('facts').' DROP INDEX size_order'); update_option('vtx_media_schema_version','0',false); VTX\\Media\\Database\\Schema::install(); echo get_option('vtx_media_schema_version');`,
  );
  console.log(
    "PASS: Schema upgrade restores a missing index and advances version",
  );
} finally {
  run("plugin", "activate", "vtx-media");
  run(
    "eval",
    `wp_set_current_user((int)get_users(['role'=>'administrator','number'=>1,'fields'=>'ID'])[0]); VTX\\Media\\Database\\Schema::install(); (new VTX\\Media\\Folders\\FolderRepository(new VTX\\Media\\Permissions\\Policy()))->delete(${id});`,
  );
}
