# Field Situations

A running record of things that actually happened to a cantor in a parish, kept
because they are the evidence behind decisions that would otherwise read as
preference. Each entry says what happened, what the tools a cantor is likely to
be using already did about it, what this application does instead, and which
code carries that property — so that a later change can tell whether it is
about to give up something a real Sunday depended on.

## 1. The parish laptop was replaced overnight

**What happened.** Material was prepared for a service. Between the preparation
and the service the parish replaced the laptop wired to the projector. The
prepared material had to be made to work again on a machine that had never seen
it — on site, shortly before the liturgy, on someone else's schedule.

**What Diatar did.** Nothing could be carried over. A service order there names
books and verses that are *installed on the machine*; a machine that has just
arrived has none of them, so the prepared order points at nothing. The
preparation was never portable in the first place — it is an index into a local
library, and the library did not come along.

**What OpenLP did.** Half the job. The saved service file carries the content
across, so the items appeared. What it does not carry is the presentation —
themes, font sizes, everything deciding how an item meets *that* projector. Those
live in the installation. The service opened and looked wrong, and the appearance
had to be rebuilt by hand to match the new machine and its beamer.

**The lesson, which is the non-obvious half.** Content portability is not
enough. A tool can move every word of a service onto a new machine and still cost
an hour, because the *appearance* was a property of the installation rather than
of the service. Any design that keeps what is shown in one place and how it is
shown in another will make you rebuild the second half on every machine you have
not met before — and you meet them at the worst possible moment, because a
laptop is replaced when the old one broke.

**What this application does.**

- **Nothing is installed.** The presenter is a URL. `plans/qr-code-login.md` is
  written around the laptop that "belongs to nobody in particular"; a laptop
  replaced overnight is that same problem at its sharpest, and the QR pairing
  makes signing in a scan rather than a password typed on a strange keyboard in
  front of the congregation — leaving nothing behind on a machine you do not own.
- **Nothing is local.** The projection, the scores it references and every
  setting live on the server. A new laptop is indistinguishable from the old one.
- **The appearance travels with the deck, not with the machine.** This answers
  the OpenLP failure directly. `projections.ratio`, `text_theme`,
  `text_size_scale` and `text_line_height` are columns on the projection;
  `projection_slides.settings_override` carries the per-row exceptions; and each
  score's own look at 16:9, 4:3 and 1:1 was chosen by its author in the score
  editor, against the very canvas `ProjectionRenderPayload` engraves onto. There
  is no theme to install and no font size to find again, because none of it was
  ever a property of a machine.
- **A different projector is not a rebuild either.** The presenter fits the
  deck's shape into whatever shape the beamer actually is and letterboxes the
  remainder, so a 16:9 deck on a 4:3 screen is correct and smaller rather than
  stretched (`resources/views/livewire/pages/projection-presenter.blade.php`).

**What it does not solve.** The replacement laptop still has to reach the
internet, and someone still has to open a browser on it. Those are the whole
remaining setup cost, and they are the cost this design deliberately accepts in
exchange for everything above.

**Where this belongs in the pitch.** It is the concrete story behind "nothing to
install, works on any laptop" — and it is stronger than the abstract claim,
because the two tools a cantor is most likely to be using fail it in *different*
ways. Diatar fails visibly and immediately, which people expect. OpenLP fails
invisibly: it looks like it worked, and the hour is spent discovering that the
appearance did not come with it.
