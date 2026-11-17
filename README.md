# Login as User

EspoCRM extension that lets administrators log in as another user — for example, to debug ACL.

## Requirements

- EspoCRM ≥ 10.0
- PHP 8.3 – 8.5

## Installation

1. Download the latest zip from the [Releases page](https://github.com/bshumylo/MC-Login-as-User/releases/latest).
2. Go to **Administration → Extensions**, upload the zip and install it.

## Usage

Open the record of an active regular user and choose **Login as User** in the dropdown of the top-right
actions. While you act as that user, the **Log Out** item of the user menu turns into **Return to My Account**.

While you act as another user, an *Acting as …* banner with a **Return to My Account** button stays on screen.
Everything you do is recorded as that user (created by, modified by, stream). The extension writes the start and the
manual end of every session (who, as whom, IP address) to the EspoCRM log at the `warning` level, so the lines are
kept with the default log settings. Action History records made in such a session keep the link to the
administrator's own login (auth token and auth log record).

### How it is secured

- **Administrators only, regular users only.** The target must be an active regular user — the same rule as the
  built-in EspoCRM "log in as another user" feature. You cannot act as yourself, as an administrator or super-admin,
  or as a portal, API, system or inactive user. API users cannot start a session. The rules are checked when the
  session starts and on every request.
- **A signed ticket, not a plain cookie.** *Login as User* asks the server (`POST /api/v1/LoginAsUser/start`), which
  sets an **HttpOnly** cookie signed with the instance `cryptKey`. The ticket names the administrator, the target
  user, the auth token of the current session and an expiry, so it cannot be forged, reused after logout or in another
  session, or read by scripts. Invalid tickets are ignored.
- **Time-limited.** One hour by default; set `'loginAsUserTtl' => 1800` (seconds, 60–28800) in
  `data/config.php` to change it. Click *Return to My Account* or log out to end it earlier.

Upgrading from the pre-1.0 versions: the old `login-as-user-id` cookie is removed automatically and is no longer used.

## Build from source

```bash
npm ci
node build --extension   # -> build/login-as-user-<version>.zip
```

Pushing a new version in `extension.json` (and `package.json`) to `main` creates a tag and a GitHub release with the
zip automatically (see `.github/workflows/release.yml`).

## License

GPL-3.0-or-later. Originally created by Apertia Tech s.r.o.
