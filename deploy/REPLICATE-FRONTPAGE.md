# Making learning.ospirax.com match local

> ## ⚠️ Superseded
>
> The Label approach below is **no longer the recommended setup**. The stat
> card numbers in that markup are hardcoded, so "My Courses" showed a fixed
> value regardless of how many courses a user was actually enrolled in.
>
> The hero is now a real template - `theme/ospira/templates/frontpage.mustache`,
> rendered by `theme/ospira/frontpage.php` - with live per-user counts. Enable
> it with a single command, no file editing:
>
> ```
> php admin/cli/cfg.php --name=customfrontpageinclude --set=/var/www/learning.ospirax.com/theme/ospira/frontpage.php
> ```
>
> Core `index.php` renders the include *instead of* the site topic section, so
> the old Label stops appearing automatically - no duplicate hero, and nothing
> to delete by hand (though removing it is tidier).
>
> The steps below remain accurate only as a fallback for recreating the hero
> by hand if the template is ever unavailable.


The theme code already deploys correctly through git. What does **not** is
anything Moodle stores in its database - and the landing page hero is one
of those things. That is why the live front page came up empty while the
Dashboard and My Courses looked right.

Almost everything below is done in the browser as a site admin. No SSH, no
terminal, no developer needed.

## What is missing on live

Checked against the live site on 2026-09-01:

| Item | Local | Live | Where it lives |
|---|---|---|---|
| Theme active | Ospira | Ospira ✅ | database |
| Footer, dashboard, My Courses | working | working ✅ | git |
| **Hero / benefits / feature strip** | present | **missing** ❌ | database (a Label) |
| Full site name | `Ospira LMS` | `ospira` | database |
| Front page items | `6` | unknown | database |

The hero is the one that matters. Everything else is cosmetic.

## Step 1 - Add the hero

Its markup is in `deploy/frontpage-hero.html` in this repo. Open that file
and copy **all** of it.

1. Log in to <https://learning.ospirax.com> as a site administrator
2. Go to **<https://learning.ospirax.com/?redirect=0>**

   Use that exact URL. `?redirect=0` matters: once you are logged in, the
   site's `defaulthomepage` setting sends `/` straight to the Dashboard,
   and you never reach the front page you need to edit.

   You are in the right place when the top of the page shows Moodle's own
   navigation - **Home / Dashboard / My courses / Site administration** -
   and an **Edit mode** toggle at top right.

   > Do **not** try to do this from `/theme/ospira/homepage.php`. That was
   > a standalone page with its own navbar and no Moodle chrome, so it has
   > no Edit mode toggle and no "Add an activity or resource" menu. It was
   > deleted from the theme; if it still loads on a server, that server has
   > not pulled the latest code.

3. Top right: turn on **Edit mode**
4. **Add an activity or resource** → **Text and media area**
5. In the editor toolbar, switch to HTML source view:
   - TinyMCE: the **`<>`** button, or **Tools → Source code**
   - Atto: the **`< >`** button on the far right of the toolbar
6. Paste the entire contents of `frontpage-hero.html` into the source view
7. Apply/OK to close the source view, then **Save and return to course**
8. Turn **Edit mode** back off

> Paste into the **HTML source view**, not the normal editor. Pasting into
> the visual editor will strip the `<div>` structure and the `ospira-*`
> classes, and you will get unstyled text.

## Step 2 - Purge caches

**Site administration → Development → Purge caches**

Moodle caches compiled theme CSS aggressively. Skip this and you may see
the markup with no styling and conclude it failed.

## Step 3 - Check it

Open <https://learning.ospirax.com> in a **private/incognito window** -
that shows you what a logged-out parent sees, and avoids cached CSS.

You should see: the purple "Science-backed guidance for growing families"
badge, the two-tone "Learning, shaped around every child." heading, the
parent-and-baby photo with the blue blob behind it, both floating stat
cards, and the four-item feature strip.

If the text appears but is unstyled, the paste went through the visual
editor instead of the source view. Delete the Text and media area and redo
step 1.

### If there is no Edit mode toggle

- You are probably on `/theme/ospira/homepage.php` or on the Dashboard
  rather than the front page. Go to `/?redirect=0`.
- Otherwise check the user menu: editing the front page needs full site
  administrator rights, not just teacher or manager.

## Step 4 - Match the remaining settings (optional)

**Site administration → General → Front page settings**

- Full site name: `Ospira LMS`
- Short name for site: `Ospira LMS`
- Front page and Front page items when logged in: set both the same as
  local, which is value `6`

**Site administration → Appearance → Logos** - if the live logo or favicon
differs, upload the files in `deploy/brand/`:

| File | Setting |
|---|---|
| `site-logo.jpeg` | Logo |
| `site-logocompact.jpeg` | Compact logo |
| `site-favicon.ico` | Favicon |

### forcelogin

Both local and live now run **`forcelogin = 1`**, by choice: this site is a
members-only portal, not a public marketing page. Visitors are sent to the
login screen, and the hero is what they see once they are in.

```
php admin/cli/cfg.php --name=forcelogin --set=1
```

Consequences, both intended: the hero is invisible to logged-out visitors,
and search engines cannot index the site.

## Why this keeps happening

The hero lives in a Label activity, so it is course *content* in the Moodle
database. `git pull` moves code; it does not move database rows. Any fresh
Moodle install therefore starts with no hero, no matter how correct the
theme is.

`deploy/frontpage-hero.html` is a verbatim export kept in git so the markup
survives a database loss and can be re-pasted. The permanent fix is to
convert it into a Mustache template rendered by a frontpage layout - then
it deploys with the code like every other page, and the stat numbers can
show real data instead of the currently hardcoded values.

## If you edit the hero later

Editing it on the live site changes only that site's database. To keep the
repo honest, copy the updated HTML back out of the source view and into
`deploy/frontpage-hero.html`, then commit. Otherwise the next rebuild loses
your changes again.
