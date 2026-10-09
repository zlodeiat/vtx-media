import { AdvancedScreen } from "./advanced/AdvancedScreen";
import { config } from "./api";
import { registerScreen, registerInspectorSection } from "./extensions";
import { AuditScreen } from "./audit/AuditScreen";
import { HealthSection, SEOSection } from "./audit/HealthSection";
registerScreen("audit", AuditScreen);
if (config.manageSettings && config.allMedia)
  registerScreen("advanced", AdvancedScreen);
registerInspectorSection("audit-health", "health", HealthSection);
registerInspectorSection("audit-seo", "seo", SEOSection);
import App from "./App";
wp.element.createRoot(document.getElementById("vtx-media-app")).render(<App />);
