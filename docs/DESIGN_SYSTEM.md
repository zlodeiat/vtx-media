# VTX Media design system

M7.2 shared component/token conventions are documented in [UI-DESIGN-SYSTEM.md](UI-DESIGN-SYSTEM.md). The earlier foundation notes below remain historical context.

Reference sources inspected read-only: vtx-redirects/assets/admin.css and includes/class-admin.php; vtx-ai-chat/assets/admin.css and includes/class-admin.php, plus their rendered admin pages during QA.

Family tokens: ink #111827, muted #64748b, canvas #f3f6fa, white surface, border #dfe5ed, action blue #3157ee, hover #2545c7, orange #ff5a1f and warm badge #fff1eb. Native system sans serif; 26px compact title, 18px panel headings, 12–13px controls, 10px uppercase section labels with tracking. Spacing 4/8/12/16/20/24/28. Panel radius 12px, control radius 7px, subtle 15,23,42 shadow. Use the family logo asset copied locally, original plugins remain untouched.

Header: logo, divider, orange VTX Labs eyebrow, product title, warm version badge, native upload/library actions. Main workspace extends the family panel into folder navigation / media browser / inspector. Blue active navigation, restrained orange top underline, no decorative analytics or future tabs.

Grid images use contain against a muted background so whole assets remain identifiable. Long filenames truncate in cards and wrap in inspector. Non-images use MIME icon and extension. Selection uses blue border/background and explicit checkboxes. Direct folder counts use muted tabular numbers. Forms have persistent labels, strong focus ring and clear dirty/saving/error feedback.

Micro transitions only (120–160ms color/border), disabled under reduced motion. No animation dependencies. Desktop: three columns; medium: narrower navigation and inspector below; mobile: collapsible navigation, two-column media grid and inspector below with a jump link. Toolbar wraps; no page horizontal overflow. Modals use native dialog focus handling, Escape and focus restoration. Drag states use dashed blue outline and text feedback; all actions have button/form alternatives.


## M3.1 progressive disclosure

Keep the established header, colors, system font, spacing and control hierarchy. Primary navigation is Library / Health / entitled Usage / administrator Advanced. Important collection counts are buttons to matching indexed results. Show human usage states and relative dates; disclose percentages, IDs and exact timestamps contextually. Compact coverage expands to real source areas; full diagnostics live in Advanced. Location drawers group records from actual source data, retain keyboard focus and preserve usable actions at narrow widths. No future Optimize/Cleanup placeholders. See UX-PRINCIPLES.md.

## M7 intelligence UI

Keep the existing VTX typography, blue actions, neutral cards and compact navigation. Smart Folders extend Library's sidebar; Permissions and Automation belong under Advanced; factual Analytics is primary. Builders reuse labeled native controls and the shared focus-managed Drawer, Button, Pager and date/byte formatters. Narrow Analytics stacks sections without horizontal overflow. No new motion library, marketing graphic, decorative chart or unrelated redesign. Roles/events use human labels; technical capability/execution identifiers stay disclosed on demand.
