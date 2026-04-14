# Template Rework (look and feel UI/UX)

You are reworking an existing homepage template for a gaming website.

Your mission is to **implement a new homepage template** using the current project stack and conventions, based on the visual and structural direction described below.

## Core goal

Rework the homepage into a **news-first gaming portal** with a strong **dark neon cyberpunk aesthetic**, while preserving the platform's overall information architecture and adapting the layout into a cleaner, more modern template.

This is a **template rework**, not a full product redesign from scratch. Reuse the existing backend data flow and existing homepage data sources where possible, but replace the current homepage presentation layer with the new structure described below.

---

## High-level design direction

Use a visual style with these characteristics:

* dark futuristic UI
* cyberpunk-inspired atmosphere
* premium editorial/gaming portal feel
* orange + dark purple accent palette
* soft glassmorphism / translucent panels
* subtle neon glows
* polished hover states
* clean spacing and good readability

Avoid:

* reusing the current homepage styles
* overly noisy effects
* overly bright green/teal dominance
* cluttered layouts
* giant empty areas

The target tone is:

* modern
* premium
* energetic
* readable
* structured

---

## Tech constraints

Implement using the project's existing stack.

Preferred implementation rules:

* keep it template-focused
* reuse current data where possible
* do not break existing homepage data contracts
* keep markup semantic
* maintain responsive behavior
* use minimal JS only where needed
* if a carousel already exists in the codebase, reuse it
* if there is an existing component system, adapt it instead of bypassing it

---

## Required homepage structure

Implement the homepage with the following sections in this order.

### 1. Header

Header structure:

* left: brand / logo
* center: **search input**
* right:

    * NEWS
    * CURATED LISTS
    * EVENTS
    * login/register icon button

Notes:

* keep the search centered visually, similar to the old site layout
* the right-side nav should be compact
* login/register should be represented by a single icon button
* keep the header elegant and not oversized

---

### 2. Hero section

Hero must be split into **2 blocks**.

#### Left block

A large featured news block:

* big visual area / image
* featured news eyebrow/label
* headline
* short teaser/summary
* CTA buttons at the bottom

Layout rule:

* text content aligned to the **top**
* CTA buttons anchored to the **bottom**

#### Right block

A vertical list of **3 to 4 compact news items**.

Each item should include:

* small thumbnail
* category label
* headline text

Important:

* apply the **same hover glow language used on the game cards** to these right-side news items
* keep the effect polished, not exaggerated

---

### 3. This Week’s Choices

This section is **not a carousel**.

It should be a fixed responsive grid of **10 game cards**.

Keep:

* section title
* icon before title
* “See all” action

Remove:

* old filter buttons like Curated / Upcoming

---

### 4. Events

This section should remain a card/banner-style section.

Requirements:

* larger event cards than before
* more like wide banners than tiny cards
* keep the section visually prominent
* keep icon before title

---

### 5. All Upcoming Releases

This section **must be a carousel**.

It should feel structurally inspired by the original homepage release strip, but visually adapted to the new style.

Desired behavior:

* horizontal release strip
* narrow-ish cards
* dense rail feeling
* arrows for navigation on desktop
* swipe / horizontal scroll on mobile
* smooth snapping behavior
* visible hint that more cards continue off-screen

Use around **12 cards** for testing/demo purposes.

#### Upcoming Releases card layout

All cards in this section must follow this exact content hierarchy:

**Row 1**

* game title
* uppercase

**Row 2**

* game type on the left
* date on the right
* both uppercase
* both visually strong / bold

**Row 3**

* platform badges

Rules:

* title should be **below the image**, not over the image
* date should **not** look like a button
* platform badges should stay compact
* game type must have color variations by enum

### Game type enum labels

Map these game types to visible colored badges:

`Enums/GameTypeEnum::labels();`

Use distinct but tasteful badge colors for each type.

### Platform labels

Support these platform labels:

`Enums/PlatformEnum::labels();`

---

### 6. Latest Added Games

This section should be reworked to feel more like a **table**, inspired by the original site's structure, but using the new visual style.

It should not be a stack of chunky cards.

Use a compact table-like layout with columns:

* Game
* Platforms
* Release Date
* Added

Each row should include:

* thumbnail + game title + optional game type badge in the first column
* platform badges in the second column
* release date text in the third column
* added time in the fourth column

Responsive rule:

* on mobile, collapse rows into stacked blocks cleanly
* desktop/tablet should keep the table feeling

---

### 7. Footer

Keep the **same content as the old footer**, but restyle it to match the new UI.

Footer content to preserve:

* copyright
* data/provider attribution
* author credit

Only restyle presentation.

---

## Card design language

Use one shared visual system across game cards.

### Shared game card style

Applies to:

* This Week’s Choices
* All Upcoming Releases
* any other similar game listing cards where appropriate

Style characteristics:

* dark card shell
* subtle border
* orange/purple neon hover glow
* soft glossy light sweep on hover
* slight lift on hover
* refined spacing
* image-first presentation
* premium storefront feel

Important:

* keep the hover glow elegant, not overdone
* the latest agreed direction is **refined shimmer**, not explosive neon

---

## Hero right-column hover style

The right-side hero news items should inherit the same hover language as the game cards:

* subtle lift
* orange/purple glow
* border shimmer
* light sweep

But tune it slightly lighter if needed so it feels appropriate for editorial/news items.

---

## Responsive behavior requirements

The new homepage must be fully responsive.

Requirements:

* no horizontal overflow
* sections must stay inside viewport
* header should collapse gracefully on narrower widths
* hero should stack cleanly on tablet/mobile
* This Week’s Choices should wrap into responsive grid columns
* Upcoming Releases carousel should remain horizontal and swipeable on small screens
* Latest Added Games table should become stacked rows on mobile

Guard against:

* broken grid overflow
* sections rendering outside viewport
* carousel pushing page width
* content clipping due to fixed widths

---

## Visual palette

Use this general palette direction:

* dark bluish-indigo background
* orange primary accent
* dark purple secondary accent
* subtle cyan allowed for minor system accents, but do not let cyan dominate

Important:

* avoid green-heavy accents
* avoid yellow-heavy look
* overall mood should feel darker purple and orange

---

## Interaction guidelines

Use tasteful interactive polish:

* card hover lift
* refined glow
* light shimmer
* smooth transitions
* no heavy/parallax gimmicks
* no exaggerated motion

---

## Implementation notes

Where possible:

* reuse current section data sources
* keep routing/actions intact
* keep search functional if already wired
* keep existing footer content source intact
* maintain accessibility basics

    * semantic headings
    * accessible buttons
    * visible focus states
    * sensible aria labels for carousel controls if applicable

---

## Acceptance criteria

The task is done when:

1. Homepage structure matches the section order described above.
2. Header uses centered search and compact right-side nav.
3. Hero is 2-column with featured story left and stacked news items right.
4. This Week’s Choices is a responsive 10-card grid, not a carousel.
5. Events uses larger banner-like cards.
6. All Upcoming Releases is a horizontal carousel with dense-strip feel.
7. Upcoming Release cards use the exact 3-row metadata layout.
8. Latest Added Games is table-like, not chunky card-like.
9. Footer keeps old content but matches the new visual system.
10. No horizontal overflow bugs remain.
11. Hover glow treatment is consistent and polished.
12. The end result feels like a cohesive homepage template rework, not disconnected section experiments.

---

## Deliverable expectations

Please:

* implement the template rework
* keep code clean and readable
* avoid unnecessary abstraction if this is a one-template rework
* document any assumptions
* mention any reused components or places where existing code was adapted
* mention any parts that require follow-up from backend/data mappings

---

## Final instruction

Please rework the homepage template accordingly, preserving existing application behavior where possible, but replacing the homepage presentation with this new structure and styling direction.
