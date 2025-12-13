Universal Hierarchical Access System with Filament Tenancy (Analysis → Improve → Implement → Verify)
🔴 IMPORTANT

DO NOT start coding immediately.
Follow phases strictly in order.

🟢 PHASE 1 — Concept & Architecture Review
1. Analyze the following concept as a system, not just code

System requirements summary:

There is NO real tenant isolation

There is ONE global “All” view (always available)

Brand switcher is ONLY a filter, not a tenant

Access is hierarchical and inherited

Access can be granted at any level

Future levels must not require schema changes

Filament tenancy is used only for UI filtering, not for data isolation

You MUST:

Validate whether the proposed model scales

Identify logical conflicts or edge cases

Propose improvements ONLY if they do not break the concept

Explicitly explain why each change is needed

❗If something is fine — say so.
❗If something is wrong — explain why and how to fix.

🟢 PHASE 2 — Finalize Architecture (no code yet)

Produce a final agreed architecture including:

A. Access model

Structure of accesses table

Meaning of accessible_type, accessible_id

How inheritance works

How “All” is computed

B. Visibility rules

Which resources user sees depending on access level

What global admins see

What guests see

C. Filament usage

How Filament tenancy will be used

How “All” differs from brand filter

How tenant switcher behaves

⚠️ No code in this phase — only explanation.

🟢 PHASE 3 — Code Analysis (existing code)

Analyze existing codebase:

Current AccessibleByUserRecursiveUniversal trait

getMorphType() logic

Access queries

Existing Filament configuration

Current tenant / team / company logic (if any)

You MUST:

Identify what can be reused

Identify what must be removed

Identify what must be refactored

Identify performance risks

🟢 PHASE 4 — Implementation
4.1 Universal Access Trait

Implement ONE universal trait that:

Supports unlimited hierarchy depth

Uses parentRelation() recursion

Uses accessible_type (no instanceof)

Supports optional caching per model

Supports:

Model::accessibleByUser($user);        // All
Model::accessibleByUser($user, $id);   // Filter


❗Trait must be reusable without modification.

4.2 Filament Integration (CRITICAL)

Use Filament v4 tenancy API
👉 https://filamentphp.com/docs/4.x/users/tenancy

Requirements:

“All” is NOT a tenant

Tenants = Brands (only for filtering)

Tenant switcher:

Hidden if user has access to only one brand

Visible if user has access to multiple brands

Switching tenant MUST:

Filter data

NOT change access rules

NOT break policies

You MUST:

Use getTenants()

Use tenant() config properly

Override ownership relationship if needed

Ensure no error like:

model does not have relationship named [access]

4.3 Resource filtering

All Filament Resources must:

Use accessibleByUser() scope

Respect current tenant (brand filter)

Work correctly in:

All

Brand A

Brand B

🟢 PHASE 5 — Seeders & Fake Data

Create seeders that generate:

Users

Global admin

Brand admin

Product-level user

Product-item-level user

Guest

Data

3 brands

Multiple products per brand

Product items per product

Access cases

Mixed access across brands

Access only to items

Access only to products

Access to full brand

🟢 PHASE 6 — Verification

Write a manual verification checklist and validate:

✔ All users see “All”
✔ Data visibility matches access
✔ Brand switcher only filters
✔ No data leaks
✔ Global admin sees everything
✔ Guest sees only allowed globals
✔ No N+1 or recursive explosion
✔ Cache invalidates correctly

🟢 PHASE 7 — Final Report

Provide:

Final architecture summary

Key decisions & reasoning

Performance considerations

Future extensions:

deny rules

role-level overrides

more hierarchy levels

🚫 ABSOLUTELY FORBIDDEN

❌ tenant = access
❌ team_id based logic
❌ company ownership model
❌ hardcoded instanceof
❌ schema changes for new levels
❌ duplicating logic per model

🏁 SUCCESS CRITERIA

The system must:

Feel simple in UI

Be powerful in access control

Be future-proof

Be understandable by another senior dev

🧠 Reminder to Cursor

This is not CRUD.
This is authorization architecture.