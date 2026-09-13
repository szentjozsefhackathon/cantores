/*
   `alpine:init` fires once, when Livewire starts Alpine on the first page of a
   visit. A split bundle (see partials/head.blade.php) that arrives on a
   wire:navigate visit is loaded long after that — its listener would never be
   called and the component it registers would never exist, so a page reached by
   navigation would raise "… is not defined" where a full page load worked.

   Registering straight away is safe once Alpine exists: Livewire waits for the
   new page's head scripts before it initialises Alpine on the swapped-in body.
*/

/**
 * Runs Alpine registrations — `Alpine.data()` and friends — as soon as Alpine
 * can take them, whether it has already started or has yet to.
 *
 * @param {() => void} register
 */
export function onAlpineInit(register) {
    if (window.Alpine) {
        register();

        return;
    }

    document.addEventListener('alpine:init', register);
}
