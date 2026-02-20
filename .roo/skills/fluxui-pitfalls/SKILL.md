---
name: fluxui-development
description: >-
  Develops UIs with Flux UI Free components based on most recent features. Activates when creating new components to avoid common pitfalls.
---

# Flux UI Development avoid pitfalls

## When to Apply

Activate this skill when:

- Creating new Flux UI components

## Basic rules

- ALWAYS create components with the command
php artisan make:livewire <component-name>
- The component is created in resources/views/components/⚡component-name. But you almost must refer to it with component-name, without the emoji.
- The component is a single file component: all PHP and Blade code must go into it
