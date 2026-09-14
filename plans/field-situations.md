# Field Situations

A running record of things that actually happened to a cantor in a parish, kept
because they are the evidence behind decisions that would otherwise read as
preference. Each entry says what happened, what the tools a cantor is likely to
be using already did about it, what this application does instead, and which
code carries that property — so that a later change can tell whether it is
about to give up something a real Sunday depended on.

After the situations comes a second list, of cases that have *not* happened and
are expected to. It is kept in the same file deliberately: the features are worth
building because of the situations, and separating the two would let the second
list drift into wishing. Anything in it that later happens for real moves up.

# Situations

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

## 2. A hotspot built by hand before every Mass

**What happens, every week.** The slides have to be advanced by whoever is at the
keyboard, and the person who knows when to advance is at the organ. So: the phone
is made an access point, the parish laptop is joined to it, and the phone opens
OpenLP's remote, which the laptop itself is serving. Then Mass begins.

**What it costs.** A network assembled by hand before every service. The cantor's
data plan carrying the laptop's traffic. A hotspot running down the battery of
the one device that has to last the whole liturgy. And the laptop pulled off the
parish network — which it is allowed to use, unlike the phone, and that asymmetry
is the entire reason the hotspot has to exist.

**Why it is worth doing anyway.** Because a remote that shows the deck is worth
that much trouble. Not a clicker: a screen showing what is up now and what is
next, which is what makes it possible to drive a service from the organ bench at
all.

**The lesson.** The hotspot is not solving a control problem, it is manufacturing
a LAN, because OpenLP is a desktop program and there is no other place for the
two devices to meet. Here there already is one. Both devices hold a session with
the same server, and the presenter cannot run without one — the laptop is online
by construction. So the whole arrangement of radios has nothing left to do, and
the remote becomes an ordinary page of the site: laptop on the parish network,
phone on its own, neither needing to see the other.

**What this application will do.** `plans/projection-remote-control.md`. The
design is small because `projection-presenter.js` already holds exactly the state
a remote needs — `index` and `blanked` — and exposes `go(index)` and nothing else.
What it adds is a row for the two devices to agree on, and the QR sign-in has
already made them the same person, so there is nothing to pair.

**What it does not solve.** OpenLP's remote keeps working with the uplink dead
and this cannot. The honest size of that: once the presenter has loaded, the deck
is engraved in the browser, so a lost connection costs the remote and not the
picture — the keyboard still drives the service.

# What the design anticipates

None of these has happened yet. They are here because they fall out of the same
properties the situations above turn on, and because keeping them beside the
evidence is what stops them becoming a wish list.

**A verse brought back mid-service.** The procession runs long, the sermon ended
early, the priest changed the offertory hymn. `excluded_slides` is decided on
Thursday; Sunday sometimes disagrees. This is nearly free here and expensive
anywhere else: the presenter engraves *every* slide and filters afterwards
(`drawn.filter((slide) => !isExcluded(slide, this.excluded))`), so revealing one
is a change of filter, not a re-engraving, and there is no stutter to pay for it.
It is also the feature that makes a remote worth more than a clicker.

**Several screens, one controller.** Nave and gallery; an overflow chapel; the
parish hall. What makes this fit rather than merely possible is that a projection
deliberately unifies nothing: each score was authored to look right at 16:9, 4:3
and 1:1 separately. So two screens of different shapes can follow the same slide
while each engraves its own geometry — the same deck, correct twice, no second
arrangement to maintain.

**The pew, and the stream.** A phone in the congregation following the cantor,
and the person watching from home doing the same. `Loan` is already the revocable
link this would ride on, and `ProjectionRenderPayload` resolves entitlement per
viewer at render time — which is exactly why it must be built on a loan and never
on an open channel: a restricted score stays off fifty phones for the same reason
it stays off the wall. Serves the overflow, the people who cannot read the far
screen, and parishes that will never mount a beamer at all.

**Rehearsal.** The schola's phones following the choirmaster on a Thursday
evening. Nothing new — the same mechanism as the pew, pointed at a smaller room.

**A correction pushed instead of pulled.** `ProjectionPresenter::reload()` exists
because there is no push today, and it is a button on a bar that is deliberately
invisible in full screen. A fix made in the sacristy at 9:58 should reach the wall
without anyone hunting for it.

**Rescue from outside the building.** Mass has started and the deck is stuck, or
showing last week's offertory. Today the person in the building has to solve it,
and that is typically the person least equipped to. Worth having as reassurance,
not advertised as a feature: it is insurance against a bad Sunday, not a weekly
workflow.

# Considered and dropped

**The remote cantor.** The idea was that a cantor serving several filial churches
could drive the projection in a church they were not standing in. It does not
survive contact with how those parishes work: one priest means the Masses are
consecutive, not simultaneous, so the cantor travels with him and is present at
each. And in the case where a second church does celebrate at the same hour with
no musician, the cantor is playing the organ at their own and cannot drive slides
for anyone. What was worth keeping from it — preparing a deck for a church you
will not visit — is not remote control at all; it is on the list above as what
being a web application already gives, and the rescue case is the only part that
needs a control channel.
