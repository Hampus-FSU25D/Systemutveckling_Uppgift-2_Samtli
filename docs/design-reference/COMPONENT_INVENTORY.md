# Component Inventory

Canonical production components should be implemented with shared PHP templates and project-owned CSS when the related feature branch needs them. Stitch generated variants are references, not production code.

| Component | Stitch screens | Canonical design decision | Variants allowed |
| --- | --- | --- | --- |
| Public header | `welcome_to_samtli`, auth forms, state pages | Centered Samtli brand, warm translucent or surface header, restrained height around 80px. | Compact mobile spacing. |
| Wordmark/logo | `samtli_wordmark`, headers | Use the Stitch wordmark/brand direction as the identity reference; keep logo treatment consistent across public and authenticated headers. | Compact icon-only use where space is constrained. |
| Authenticated header | `home_samtli`, `samtli_home_feed`, `discover_*`, group screens | One shared header with logo, primary nav, optional search, notifications and account trigger. | Active nav state, authenticated user initials, mobile collapse. |
| Mobile header/navigation | Screens using hidden `md:` nav variants | Same hierarchy as desktop with condensed navigation and tap-sized controls. | Mobile menu disclosure. |
| Page container | Most screens | Center content in max 1120px container with 24px desktop gutters and 16px mobile margins. | Narrow form container for auth/account screens. |
| Primary button | Forms, group actions, admin actions | Cobalt/secondary background, white text, 4-8px radius depending context, label weight 600. | Full-width form submit, icon-leading action. |
| Secondary button | Cancel, explore, return actions | White or transparent surface with subtle warm-gray border and near-black text. | Disabled state and compact toolbar use. |
| Text button | Login/register links, footer/action links | Cobalt or near-black text, underline on hover only where link-like. | Destructive text variant. |
| Destructive button | Logout/error-adjacent actions | Muted red text or error container treatment, never only client-side. | Icon-leading logout. |
| Input | Login, register, account, group/discussion forms | White or lowest surface background, 1px warm-gray border, 4px radius, cobalt/primary focus border. | Password visibility affordance. |
| Textarea | Group and discussion creation, reply composer | Same visual treatment as inputs, larger vertical padding, no framework-specific resize behavior required. | Taller composer variant. |
| Form field | Auth/account/create screens | Label, control and helper/error text grouped consistently with 8-16px rhythm. | Required/helper/error states. |
| Validation message | `log_in_error_samtli`, `application_states_ui_feedback_samtli` | Muted red text/container with clear association to field or form. | Inline field error, form-level alert. |
| User avatar | Feed, discussion and account contexts | Square initials avatar with 4px radius and muted earth-tone fills. | 32px compact, 40px list, larger profile/admin contexts. |
| Group icon | Discover, home sidebar, group pages | Stroke icon or minimal tile in neutral container, not photo-heavy by default. | Category-specific icon. |
| Membership badge | Group/discover screens | Small neutral or cobalt-tinted badge with label-md/label-sm typography. | Pending, member, joined. |
| Administrator badge | Admin/member screens | Small distinct badge, likely secondary-container with on-secondary-container text. | Current user, other administrator. |
| Status badge | State/admin screens | Token-based semantic badges for pending, approved, expired, used and unavailable. | Success, warning, error. |
| Group card/row | `discover_*`, `home_samtli`, feed sidebar | White card or row with subtle border, group icon, title, description/meta and one clear action. | Guest, member, pending request. |
| Discussion row | `samtli_home_feed`, group screens | Avatar/icon, title, excerpt/meta, reply count and group context with hairline separation. | Feed row, group row, compact sidebar row. |
| Discussion post | Discussion detail screens | Readable post body, author avatar, metadata and restrained dividers. | Original post, reply post. |
| Reply composer | Replying discussion screen | Textarea with clear submit action below or aligned right; preserve content focus. | Empty, focused, submitting. |
| Admin navigation/tabs | `manage_*` screens | One shared admin tab/nav pattern for overview, join requests, members and invitations. | Active tab, count badge. |
| Empty state | Empty group, empty requests, state screens | Centered or section-level neutral panel, concise heading, optional icon and one next action. | Page-level, list-level. |
| Success state | Joined/invitation feedback screens | Warm minimal confirmation with semantic success color used sparingly. | Inline alert, page confirmation. |
| Error state | Login error, invitation unavailable, 404 | Clear message, muted red only where error-specific, one recovery action. | Field-level, page-level. |
| Invitation card | Invitation screens/admin invitations | Card with group context, expiry/single-use state and primary accept/copy action. | Valid, expired, used, copied. |
| Account menu | Authenticated header/account screen | Initials avatar trigger with small menu; account page uses same identity primitives. | Open/closed, logout visible. |

When Stitch varies the same shared component between screens, use the common design intent: warm surface, near-black text, cobalt actions, low-contrast borders, modest radii, no heavy shadows, and component reuse across pages.
