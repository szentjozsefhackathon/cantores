---
name: fluxui-pitfalls
description: >-
  Enhances Flux UI development with best practices and latest features. Activates when working on Flux UI components to avoid common pitfalls.
---

# Flux UI pitfalls to avoid

## When to Apply

Activate this skill when:

- Creating new Flux UI components
- Modify existing Flux UI components

## Basic rules

- When creating new components ALWAYS use
php artisan make:livewire <component-name>
- The component is created in resources/views/components/⚡component-name. But you almost must refer to it with component-name, without the emoji.
- The component is a single file component: all PHP and Blade code must go into it
- The ⚡ emoji means it is a _Livewire_ component

# Rules when displaying flash messages
- Use <x-action-message> for flash messages, not custom code. This ensures consistent styling and behavior across the app.
- Trigger messages with $this->dispatch('event-name', message: 'Message text') in the component class, and listen for them with on="event-name" in the Blade template.