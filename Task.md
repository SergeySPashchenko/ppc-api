Goal: Implement multi-level access control in Filament with “All view” for users and brand/product filtering.

Requirements:

Main screen: All accessible resources are visible regardless of level (Brand, Product, ProductItem, etc.).

Sub-screens: Show only resources for the selected brand/product. If a user has access to all items under one brand, no sub-screen needed, only “All”.

Global admins: Can see everything, can switch brands, “All Brands” always accessible.

Roles & access:

Users may have access to a brand, specific products, or lower-level items.

Access is inherited unless explicitly restricted.

If a user has edit rights at product level, they can edit product items unless restricted.

Caching: Cache accessible IDs per user and model. Allow toggling cache per model.

Reusable logic:

Use AccessibleByUserTrait (or similar) with recursive parent relation support.

Each model defines public static function getMorphType(): string.

Filament integration: Apply tenancy using Filament 4.x API for users and teams.

Data: Generate fake data to verify multi-level access and “All view” behavior.

Optimization: Avoid loading unnecessary records, support filtering by brand or product dynamically.

Tasks for Cursor:

Analyze plan, suggest improvements if needed.

Update models & traits with recursive, cached access.

Integrate with Filament tenancy API.

Create fake data for testing.

Verify that main screen shows “All accessible resources” and sub-screens filter correctly.

Ensure global admins have full visibility and can switch brands.