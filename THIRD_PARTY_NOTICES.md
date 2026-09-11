# Third-party notices

The Elancer workspace adapts layout patterns from TailAdmin's free React dashboard:
https://github.com/TailAdmin/free-react-tailwind-admin-dashboard

MIT License

Copyright (c) 2023 TailAdmin

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Notification dropdown adaptation (2026-09-10)

Source files in the TailAdmin free React dashboard (main, retrieved 2026-09-10):

- src/components/header/NotificationDropdown.tsx
- src/components/ui/dropdown/Dropdown.tsx

Local files: resources/js/components/notification-dropdown.tsx and
resources/js/components/tailadmin-dropdown.tsx. Adaptations preserve the demo SVGs
and dropdown composition, use Elancer theme tokens, add accessible focus/dismissal
and viewport sizing, and replace fictional demo notifications with an empty state.
The MIT license above applies to these adaptations.

## Onboarding form components (2026-09-10)

The files under resources/js/components/tailadmin/ adapt these source files from
https://github.com/TailAdmin/free-react-tailwind-admin-dashboard (main, retrieved 2026-09-10):

- src/components/form/input/InputField.tsx
- src/components/form/input/TextArea.tsx
- src/components/form/input/Radio.tsx
- src/components/form/Label.tsx
- src/components/ui/button/Button.tsx

The demo component structure is retained. Adaptations use Elancer semantic colors,
forward native form attributes, add keyboard focus treatment and button types,
and remove redundant upstream type unions. The MIT license above applies.

## Category management (2026-09-10)

Official main source retrieved and inspected on 2026-09-10:

- src/components/tables/BasicTables/BasicTableOne.tsx
- src/components/ui/table/index.tsx
- src/components/form/form-elements/DefaultInputs.tsx

Adaptations: resources/js/components/tailadmin/table.tsx and
resources/js/pages/admin/categories/{index,form}.tsx. Preserve the demo table
wrappers, mapped rows and form composition; replace order data with bilingual
categories, adapt colors to Elancer tokens, add Inertia pagination/actions,
validation, RTL, wrapping and native accessibility attributes. Reuse the existing
TailAdmin Input, Label, Button and ComponentCard adaptations. MIT license above applies.

## Super-admin access management (2026-09-11)

resources/js/pages/admin/administrators/ adapts the previously retrieved and
inspected TailAdmin BasicTableOne.tsx and DefaultInputs.tsx through the existing
Elancer category table/form adaptations. It reuses the TailAdmin table, Input,
Label, TextArea, Button and ComponentCard components. Adaptations add bilingual
account search, protected role actions and access-change reasons using Elancer
tokens and Inertia. The MIT license above applies.
