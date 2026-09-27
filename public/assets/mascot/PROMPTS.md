# Tappy - CSCS Tap n Track mascot

This folder is where the mascot artwork lives. The files are **not** in version
control; paste them in as you generate them.

Nothing breaks while this folder is incomplete - every mascot on the site is
wrapped in `.mascot`, and if an image cannot load the wrapper hides itself. Add
the images one at a time and the site improves as you go.

**This document lists only the artwork that still needs generating.** The
original 16 poses and 8 stickers are already done and are not described here.
What exists is reported by `php tools/mascot_assets.php`, which prints
per-class counts and exits 1 until every documented file is present.

---

## 0. Where the artwork actually stands

**Everything in section 3 is generated and installed except the four
"Using the Portal" posters** added in section 7b. `php tools/mascot_assets.php`
currently reports 20/20 poses, 16/16 stickers, 2/2 logos, 4/8 posters, 1/1 band,
1/1 pattern, all with a real, working alpha channel.

| State | Detail |
|---|---|
| 20 poses, 16 stickers, 2 logos, 4 posters, band, pattern | Real alpha, trimmed and downscaled. Usable as is. |
| `burst.png` -> **`bust.png`** | The head-and-shoulders portrait was saved under the wrong name. Renamed. Nothing referred to `burst.png`. |
| `ChatGPT Image Sep 27, 2026, 02_48_35 PM.png` | A second full-body *waving* pose, a near-duplicate of `hero.png` with its own ID badge. Unused. **Safe to delete** - it is often held open by an image viewer, which locks it on Windows; close the preview and delete it by hand. |
| 4 files | The `poster-portal-*` set, not generated yet. Section 7b. This is the only reason the validator currently exits 1. |

### The stickers were pasted in after the optimiser last ran

The eight original stickers arrived at **1254x1254 and roughly 1 MB each** -
about 9 MB of artwork that renders at **28-64 px**, so each carried a few hundred
times the pixels it could ever show.

`php tools/mascot_optimize.php` has now been run over the whole folder:
**14.47 MB -> 8.61 MB** (about 7.0 MB once the stray file above is deleted).
The stickers alone went from ~9.0 MB to ~2.9 MB.

Two bugs in the tooling were found and fixed while doing that:

- **A dimension check is not a weight check.** The optimiser only tested pixel
  dimensions, so a file arriving at exactly the right size but megabytes of
  weight was skipped forever - which is how 9 MB of stickers got shipped. It
  now re-encodes on weight too (`--max-weight`, default 500 KB), and only ever
  scales *down*.
- **The "painted checkerboard" warning sampled the wrong pixels.** It walked the
  outer ring of a 24x24 grid, which sits about 2% *inside* the image, not on
  the border. Before trimming, that ring landed in the stickers' transparent
  margin and read "ok"; after trimming it landed on the stickers' own white
  outline and reported three clean files as defective. It now walks the true
  outer border and reports a percentage of it.

`bust.png` is deliberately left at 461 KB. Measured, re-encoding it yields
457 KB - a 1% gain - and at 525x639 it simply carries 1.4x the pixels of
`reading.png`. That is not worth a resample round-trip on the artwork used for
every modal header.

### After pasting new art: run the optimiser

```
php tools/mascot_optimize.php --dry-run   # report only
php tools/mascot_optimize.php             # trim + downscale + re-encode
```

It trims the transparent margin, resamples the long edge to 640 px (still ~2x the
largest size the app renders, so it stays crisp on high-DPI screens) and
re-encodes.

**It never crops a `pattern-*` file.** A tiling texture has to keep every pixel
of its canvas; trimming one to its alpha bounds leaves a small motif with a hard
edge that repeats across the page as a visible box. The optimiser recognises the
prefix and keeps the full canvas by default, and `--no-trim` does the same for
anything else whose canvas is the artwork.

---

## 1. Generate the character sheet FIRST

Before generating any pose, produce one **turnaround sheet** of the character in
a neutral standing pose (front, 3/4 and side). Keep it. Use it as the reference
image for every later generation - this is what stops the face, hair and uniform
from drifting between poses.

---

## 2. The master prompt (paste verbatim every time)

Use this for every character: the 3 new poses, the footer peek, the posters, the
divider band and the logo marks. **Only the final `POSE:` block ever changes.**

```
3D stylized cartoon character in Pixar / Blender style, soft studio lighting,
rounded friendly forms, subtle subsurface scattering, gentle ambient occlusion
(clothing and skin creases only - no cast shadow onto the background),
slight depth of field.

CHARACTER - a cheerful Filipino elementary school student, 10-11 years old:
- Short neat black hair with a soft side part and one loose strand on the forehead
- Large round warm brown eyes, thick simple eyebrows, small upturned nose,
  wide open smile, light blush on the cheeks, light tan skin
- Rounded chunky proportions, oversized hands and shoes, head slightly larger
  than realistic (about 1:3 head-to-body), soft rounded silhouette that still
  reads clearly when shrunk to 64 pixels
- Uniform: white short-sleeved polo with royal-blue collar and blue sleeve trim,
  navy blue trousers or pleated skirt, royal blue backpack,
  small enamel school ID badge pinned to the chest
- Props: slim indigo smartphone with a glowing amber "tap" ripple on the screen,
  plus a light-blue student ID card on a lanyard

PALETTE - use these exact hex values, do not drift between images:
  deep blue #1e40af | sky blue #3b82f6 | amber #f59e0b | warm yellow #fbbf24
  emerald #059669 | off-white #f8fafc | slate #334155

STYLE RULES:
- 3/4 front camera angle, eye level, ~35mm perspective
- Character centered with generous empty margin on every side
- Matte smooth surfaces, no glossy plastic sheen
- No text, no watermark, no logo, no border, no frame
- 8k detail, sharp focus

BACKGROUND - TRANSPARENT PNG, THIS IS REQUIRED:
- The character must have a fully transparent background. No backdrop, no
  floor, no wall, no vignette, no colour of any kind behind the character.
- Do NOT include a drop shadow on the ground, a glow, a halo, or a backdrop
  panel - only the character itself.
- NEVER accept a painted checkerboard. When a generator cannot emit alpha it
  "helpfully" draws the transparency grid INTO the picture - those are ordinary
  opaque pixels, they are baked into the file for good, and they are impossible
  to key out afterwards. Reject the image and re-roll it.
- NEVER accept a dark or gradient backdrop, not even a soft vignette. The same
  file has to composite onto a dark blue sign-in card, a white page and a
  coloured alert banner.
- The image is placed on a dark blue sign-in card, a dark navigation sidebar
  and tinted alert banners, so ANY opaque background will show as a visible
  pale rectangle and look broken.
- If your generator cannot output alpha, generate on a pure chroma-key green
  (#00FF00) screen with NO shadows, then key the green out. Clean edges and
  no colour fringing around the hair and fingers are essential.
- PNG, 32-bit, alpha channel intact. Not a JPG - JPG cannot be transparent.

Treat this whole block as a character sheet. Keep the SAME face, hair, outfit,
proportions and lighting in every generation. Change ONLY the pose block below.

POSE: <swapped per image>
```

**Consistency checklist before you accept an image**

- [ ] **Background is genuinely transparent** - open the file over a dark
      surface and confirm you can see through to it
- [ ] No ground shadow, glow, halo or backdrop panel left behind
- [ ] No green fringing around the hair, fingers or edges
- [ ] Collar is royal blue, not white or black
- [ ] Hair part is on the same side
- [ ] Eye shape and colour match the sheet
- [ ] Palette hexes unchanged
- [ ] Same 3/4 camera angle
- [ ] Still readable when shrunk to 64 px

**Quickest transparency check:** drop the file onto a dark grey or black
background. Anything opaque, fringed or haloed is obvious immediately, and it is
much harder to spot on the light `#f8fafc` you were probably previewing on.


---

## 3. The 24 files to generate

| # | File | Class | Canvas | Prompt in |
|---|---|---|---|---|
| 1 | `megaphone.png` | pose | 1024x1536 | master, section 4 |
| 2 | `envelope.png` | pose | 1024x1536 | master, section 4 |
| 3 | `medal.png` | pose | 1024x1536 | master, section 4 |
| 4 | `footer-peek.png` | pose | 1536x1024 | master, section 5 |
| 5 | `logo-mark.png` | logo | 1024x1024 | master, section 6 |
| 6 | `logo-mark-mono.png` | logo | 1024x1024 | master, section 6 |
| 7 | `poster-enroll.png` | poster | 2560x1097 | master, section 7 |
| 8 | `poster-about.png` | poster | 2560x1097 | master, section 7 |
| 9 | `poster-announce.png` | poster | 2560x1097 | master, section 7 |
| 10 | `poster-support.png` | poster | 2560x1097 | master, section 7 |
| 11 | `divider-band.png` | band | 2400x400 | master, section 8 |
| 12 | `pattern-doodle.png` | pattern | 512x512 | standalone, section 9 |
| 13 | `sticker-saved.png` | sticker | 1024x1024 | standalone, section 10 |
| 14 | `sticker-upload.png` | sticker | 1024x1024 | standalone, section 10 |
| 15 | `sticker-question.png` | sticker | 1024x1024 | standalone, section 10 |
| 16 | `sticker-error.png` | sticker | 1024x1024 | standalone, section 10 |
| 17 | `sticker-pending.png` | sticker | 1024x1024 | standalone, section 10 |
| 18 | `sticker-settings.png` | sticker | 1024x1024 | standalone, section 10 |
| 19 | `sticker-audit.png` | sticker | 1024x1024 | standalone, section 10 |
| 20 | `sticker-backup.png` | sticker | 1024x1024 | standalone, section 10 |
| 21 | `poster-portal-login.png` | poster | 1600x1600 | master, section 7b |
| 22 | `poster-portal-grades.png` | poster | 1600x1600 | master, section 7b |
| 23 | `poster-portal-schedules.png` | poster | 1600x1600 | master, section 7b |
| 24 | `poster-portal-updates.png` | poster | 1600x1600 | master, section 7b |

All files are **transparent PNG**. The canvas is the minimum to generate at; the
optimiser trims and downscales afterwards.

---

## 4. The 3 new poses

Master prompt, and swap **only** the final `POSE:` line.

| File | `POSE:` line |
|---|---|
| `megaphone.png` | `Full body holding a bright blue megaphone up to the mouth with one hand, other arm raised in a friendly wave, excited open-mouth announcement expression, small sound-wave arcs radiating outward from the megaphone` |
| `envelope.png` | `Full body hugging an oversized blue airmail envelope with a visible flap, delighted secretive smile, head tilted, one hand holding the envelope to the chest, small paper plane floating above` |
| `medal.png` | `Full body wearing a large gold medal on a wide royal-blue ribbon around the neck, one hand lifting the medal proudly, chin up, beaming proud smile, tiny sparkles around the medal` |

`megaphone` goes on the announcements page header, `envelope` on password reset
and mail verification, `medal` on the honor roll and awards.

> Do **not** put a large image in the landing page's announcement strip. It is a
> marquee that repeats itself four times per group, so the file would be fetched
> repeatedly for no gain. It keeps its existing icon.

---

## 5. The footer peek

Master prompt, and replace the `POSE:` block with this. **3:2 landscape,
1536x1024 or larger.**

```
CROP: upper body only, sliced cleanly by the BOTTOM EDGE of the frame.
Both forearms horizontal, hands resting on an invisible ledge just below
the frame - as if leaning over it.
The bottom edge must cut the character off cleanly. Do NOT draw a full
body, a ledge, a surface, a table or any ground.
POSE: warm welcoming smile, looking slightly down and forward at the viewer.
```

The cut-off is **baked into the artwork on purpose**. CSS only bottom-aligns the
image to the footer's top border and clips nothing; faking it with
`overflow: hidden` on the footer would clip the art in the wrong place and would
also clip any focus ring inside the footer.

---

## 6. The 2 logo marks

Master prompt, and replace the `POSE:` block with this. **1024x1024.**

A separate class from the poses on purpose: flat vector, not a lit 3D render,
because a shaded head turns to mush at 24 px in a browser tab.

```
STYLE: flat polished vector emblem, single uniform outline weight, minimal
shading - optimised for legibility at 32 px, not for realism.
CROP: head and shoulders, facing straight ahead, centered, generous even
margin on all four sides.
POSE: calm, confident, friendly closed-lip smile.
NO props, NO text, NO background, NO drop shadow.
```

| File | Extra |
|---|---|
| `logo-mark.png` | as-is, full colour |
| `logo-mark-mono.png` | append: `COLOUR: one single flat colour, #1e40af, on a fully transparent background. No shading, no highlights, no gradients, no outlines, no second colour.` |

`mono` is for print, PDF and ID cards, where the artwork lands on white paper
and colour cannot be relied on.


---

## 7. The 8 posters

### 7a. The four ultrawide posters

Master prompt, and replace the `POSE:` block with this.
**21:9 ultrawide, 2560x1097 or larger.**

```
CANVAS: 21:9 ultrawide, 2560x1097 or larger, transparent background.
COMPOSITION: the character occupies ONLY the <LEFT|RIGHT> third of the
frame, full body, complete and uncropped. The other two thirds MUST be
completely empty transparent space - no props, no gradients, no ground
shadow, no decorative marks of any kind. That empty space is reserved
for live HTML text and must stay clear.
POSE: <per file>
```

| File | Character in | `POSE:` line |
|---|---|---|
| `poster-enroll.png` | **right** third | `Full body, one arm extended toward the empty space to the left in an inviting welcoming gesture, big open smile, other hand on hip` |
| `poster-about.png` | **left** third | `Full body sitting cross-legged holding an open book, attentive and curious, looking toward the empty space to the right` |
| `poster-announce.png` | **right** third | `Full body holding a bright blue megaphone up to the mouth, excited announcement expression, facing the empty space to the left` |
| `poster-support.png` | **left** third | `Full body standing proudly holding a laptop showing a glowing blue screen, presenting it toward the empty space to the right` |

**Do not bake a headline into a poster.** The empty space is filled with live
HTML, so the text stays translatable, resizable and readable by a screen reader.

> The landing hero is **already a photo slideshow** (`.hero-slideshow`, 1983x793
> banners), so posters do not go there. They belong in the CTA section and the
> `.section-light` / `.section-dark` backgrounds.

### 7b. The four "Using the Portal" posters

These four are a **deliberate variant of the poster class, not a fifth ultrawide.**
They are **1:1 and self-contained**, with no reserved empty third.

The reason is the layout they sit in. `partials/portal_overview_sections.php` puts
them in `.portal-poster-grid`, four across, each card holding a numbered badge,
the artwork, and a **speech bubble** carrying the caption underneath. There is no
adjacent text for an empty third to make room for, and at a quarter of a wide
viewport a 21:9 frame is so short the character stops being readable. Fill the
frame instead and let the bubble sit under it.

**The character must fill the frame.** The box crops a 1:1 with `object-fit: cover`,
so a small figure lost in empty margin is what makes the card look undersized. Aim
for the character to occupy roughly 80-90% of the frame height, standing dead
centre, with only a thin even margin. Nothing important near the left or right
edge, because those are the sides that get cropped.

Master prompt, and replace the `POSE:` block with this.
**1:1, 1600x1600 or larger, transparent background.**

```
CANVAS: 1:1, 1600x1600 or larger, transparent background.
COMPOSITION: one self-contained scene, full body, standing DEAD CENTRE and
filling the frame - roughly 80-90% of the canvas height, with only a thin even
transparent margin all round. Keep every prop within the middle 70% of the width;
the outer edges are cropped away. Unlike the 21:9 posters in 7a there is NO
reserved empty space - and equally there is NO baked text of any kind: no
headline, no caption, no speech bubble, no signage, no readable lettering
anywhere in the image. The speech bubble in the page is live HTML drawn
underneath, so every word stays translatable, resizable and readable by a
screen reader.
POSE: <per file>
```

| File | `POSE:` line |
|---|---|
| `poster-portal-login.png` | `Full body standing at an open laptop, one hand resting on the keyboard, calm and welcoming, facing the viewer` |
| `poster-portal-grades.png` | `Full body holding up a clipboard of marks beside a small bar chart, pleased and proud, presenting it forward` |
| `poster-portal-schedules.png` | `Full body beside a wall calendar and a small round clock, one hand pointing at the calendar, organised and helpful` |
| `poster-portal-updates.png` | `Full body holding a tablet with a single glowing notification badge, other hand raised in a small wave, attentive and encouraging` |

Drawn by `mascot_poster_for('portal-login' | 'portal-grades' | 'portal-schedules' | 'portal-updates')`.

Until a file exists the slot renders a placeholder naming the file, so this
section is safe to ship before the art is generated and needs no switch flipped
afterwards. Once all four are in, `php tools/mascot_assets.php` reports
`poster: 8/8 present` and exits 0 again; while any is missing it exits 1 with a
"MISSING POSTER (referenced by the app, not generated yet)" list, which is that
report doing its job rather than a failure.

> The keys are registered in **two** allowlists, and a file present on disk but
> missing from either is reported UNDOCUMENTED: `mascot_poster_for()` in
> `app/Helpers/mascot_helper.php`, and `MASCOT_POSTERS` in
> `tools/mascot_assets.php`. Adding a fifth portal poster means adding to both.

---

## 8. The divider band

Master prompt, and replace the `POSE:` block with this.
**6:1, 2400x400 or larger.**

```
POSE: Full body lying on the stomach, chin propped on both hands, feet up
behind, elbows on the bottom edge of the frame, relaxed cheerful expression,
reading an open book flat on the ground in front, facing the viewer. Very
wide shallow composition.
```

---

## 9. The seamless pattern

**Standalone prompt - no character rendering.** 512x512.

```
TILE: a seamlessly repeating 512x512 square tile of small doodle motifs.
MOTIFS: the character's head as a simple line icon, an open book, a pencil,
a five-point star, an ID card, a graduation cap.
DISTRIBUTION: evenly spaced in a loose regular grid. Every motif must sit
ENTIRELY inside the tile with a clear margin - nothing may touch or cross
the tile edge, so the repeat never shows a sliced shape.
STYLE: thin single-weight line art, colour #1e40af only, fully transparent
background. Low contrast and subtle - this sits behind text at low opacity.
NO text, NO colour fills, NO large character, NO shaded shapes.
```

**On "seamless":** image generators rarely produce a truly seamless tile. The
distribution rule above sidesteps that by requiring every motif to sit fully
inside the margin, which tiles without visible seams *by construction* - at the
cost of a regular repeating rhythm. That is the reliable version. A true offset
tile is manual work in an image editor.

Rendered at 5% opacity and hidden below 768 px.

---

## 10. The 8 new stickers

**Standalone prompt - no character.** 1024x1024, transparent.

```
Flat sticker badge, glossy 3D-vector style, chunky rounded shapes, thick soft
outline, subtle inner bevel and soft drop shadow, centered on a fully
transparent background. Colors limited to #1e40af, #3b82f6, #f59e0b, #fbbf24,
#059669, #ffffff. Playful but clean, readable at 48 pixels, no photo realism.
NO background scene, no watermark, no extra elements.

STICKER: <swapped per image>
```

| File | `STICKER:` line | Reaches |
|---|---|---|
| `sticker-saved.png` | `A green rounded checkmark badge reading "SAVED"` | every save confirmation |
| `sticker-upload.png` | `A blue rounded badge showing a thick upward arrow over a document, reading "UPLOAD"` | all material/photo upload flows |
| `sticker-question.png` | `A blue rounded circle badge with a bold "?" and a small lightbulb` | FAQ, citizens charter, help |
| `sticker-error.png` | `A red rounded badge with a bold "X" and a cracked corner` | error states, distinct from the amber alert badge |
| `sticker-pending.png` | `An amber rounded badge showing an hourglass, reading "PENDING"` | awaiting approval / verification |
| `sticker-settings.png` | `A slate rounded badge showing a gear, reading "SETTINGS"` | settings and configuration pages |
| `sticker-audit.png` | `A slate rounded badge showing a shield with a magnifying glass` | the audit log |
| `sticker-backup.png` | `A blue rounded badge showing a database cylinder with a downward arrow` | the backup pages |

`#ffffff` is deliberately in the allowed palette: the stickers have a white
outline and bevel. The validator samples the true image border, so a white
outline is not mistaken for a painted background.


---

## 11. Where the code meets the artwork

| Ask for | Helper | Needs |
|---|---|---|
| `mascot_img(['name' => ...])` | a character pose | the 19 poses |
| `mascot_sticker_for('saved')` | a sticker by **meaning** | the 16 stickers |
| `mascot_dialog_art('mail')` | the right pose for a dialog | an existing pose, **no new art** |
| `mascot_logo_for('mono')` | an icon-scale mark | the 2 logos |
| `mascot_poster_for('enroll')` | a poster composition | the 8 posters - 4 ultrawide (7a), 4 four-by-three (7b) |
| `mascot_band_url()` | a CSS background URL | `divider-band.png` |
| `mascot_pattern_url()` | a tiling CSS background | `pattern-doodle.png` |

Every one of these returns an empty string when the file is not installed, so a
page with no artwork still lays out correctly. **Paste art in any order.**

`mascot_dialog_art()` is why there is no "images for modals" section here:
`search`, `reading`, `laptop`, `worried`, `thinking`, `celebrate` and `thumbs-up`
already existed and were simply never mapped to a dialog. Generating modal art
would have duplicated artwork the project already owns.

---

## 11b. What each pose is FOR

**Read this before generating or regenerating any pose.** A pose is not
decoration, it is a word. The dock picks one per page, so two pages sharing a
pose means two pages saying the same thing without anyone writing a word.

`point-right` used to be the catch-all fallback for any page with no line
mapped, which put the same pointing hand on 23 of 53 pages at once. That is the
failure this table exists to prevent: **one meaning per pose.**

| Pose | Means | Used for |
|---|---|---|
| `hero` | the front door | the public landing page, a warm welcome |
| `bust` | a person | you, or someone you manage: profiles, teachers |
| `search` | looking something up | students, records, ID lookups |
| `chart` | numbers, trends, anything measured | dashboards and analytics pages |
| `reading` | a document you read | subjects, materials, logs, reports |
| `laptop` | routine system work | forms, uploads, settings, backups |
| `star` | something shown off, or rated | progress, platform feedback |
| `medal` | merit: marks, achievement, approval | grades, report cards, GAD |
| `envelope` | mail arrives here | messages, reset links |
| `worried` | something needs a human | password resets, blocked states |
| `celebrate` | an achievement | content you are publishing |
| `megaphone` | broadcasting to other people | announcements |
| `tap-card` | identity issued or checked | ID cards, attendance marking |
| `thumbs-up` | consent, confirmation, done | sign-up and registration forms |
| `thinking` | "not sure?" | help and password-recovery pages |
| `point-up` | look up, something arrived | notifications |
| `point-down` | a list sitting below | sections, children, class lists |
| `sleeping` | nothing here | shared empty states only |
| `point-right` | **POINTING AT THE MENU. Nothing else.** | the sidebar, and only the sidebar |

`MascotPageCopyTest` enforces all of this: it fails if any reachable page falls
through to the generic line, if a pose is missing from this folder, if one pose
covers more than 6 pages, or if `point-right` is used for anything but the menu.

### The publishing pages

Four public pages take their content from the admin side: **CHILDPRO**, **GAD**,
**Programs** and **Projects**. Each is a separate page, a separate tab in the
admin, and a separate line for Tappy - not one shared "Programs and Projects"
line, because that would be wrong on a page called "Projects".

Each of those admin pages also has a card for writing Tappy's message for that
public page. So the artwork to keep consistent is `celebrate` and `megaphone`
(the publishing pages) alongside `hero`.

---

## 12. Rules for the code

- The folder may legitimately be incomplete; nothing breaks.
- Keep the exact file names. The helpers slugify whatever you pass, so a name
  must be a **bare slug with no `.png` on the end** - passing `logo-mark.png`
  would look for `logo-mark-png.png`.
- Run `php tools/mascot_assets.php` after pasting anything in. It exits 1 while
  a documented file is missing, and with `--strict` it also fails on an opaque
  file. `MascotAssetsTest` runs the same alpha check in CI when
  `MASCOT_STRICT_ASSETS=1`.
- **Transparent background is required, not optional.** See section 2.
- PNG with a real alpha channel. No JPG - JPG cannot be transparent.
- The artwork is composited over a dark blue sign-in card, a dark sidebar and
  tinted alert banners, so leftover backdrop, ground shadow or glow will be
  visible. Key those out.
- If a pose would be hard to cut out cleanly (a hand behind the body, a prop
  crossing the silhouette), regenerate it rather than shipping a muddy edge - it
  is far more noticeable on a dark background than a light one.

