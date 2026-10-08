# Office Contest

A self-hosted website for office contests: Halloween costume contests, ugly sweater showdowns, chili cook-offs, cookie bake-offs and anything else people vote on. Coworkers scan a QR code, enter with a photo, and vote from their phones. No accounts, no app to install.

It also has an **AI photobooth**: a tablet or webcam PC that takes a photo and turns people into zombies, vampires, elves or snow globes.

**[Install guide →](docs/INSTALL.md)** · [Clickable design mockup](docs/mockup.html)

## Features

**For everyone (on their phone)**
- **Home:** live countdown, overall top 3 and the leader in each category (ties share a place).
- **Enter:** take a photo, add your name, department and costume name.
- **Vote:** type your name once, pick one favorite per category, and change your mind until voting closes.
- **Gallery:** contest entries, photobooth pictures, and videos (uploads up to 300 MB, or YouTube links).

**Photobooth (tablet or webcam PC)**
- Just me or group photo, countdown, retake, pick a look, before and after, QR code to save it to your phone.
- Restyled by OpenAI's image API. The key stays on the server, encrypted. Demo mode works without a key.
- Looks and AI instructions editable per contest; daily photo limit to cap costs.

**Contest modes:** Halloween, Holiday party and General. Each sets the colors, wording, starter categories and photobooth looks. Each contest can also have a page background: the built-in spooky Halloween scene, none, or your own image.

**Admin (secret link + PIN)**
- Company logo, name and brand color; department list; time zone.
- Contests: dates, categories, event details, entry approval (off by default), live vote counts on or off, who can add videos.
- Entries: approve, hide or delete, with live results.
- Voters: every voter's name, device and network, with **duplicate-vote flags** (same name on two devices, similar names, same phone in private mode, bursts from one network). Don't-count / count-again per voter.
- Downloads: results, voter list and every vote as Excel-friendly CSV.
- Printable QR poster; close voting early; reset votes.

## Rules

- One vote per device per category. Voters type their name so duplicates can be spotted.
- The overall winner is the total of an entry's votes across all categories.
- One entry can win several categories; ties share a place.

## Requirements

PHP 8.2+ (with `pdo_mysql`, `gd`, `curl`, `mbstring`, `fileinfo`, `sodium`, `exif`), MySQL 5.7+ or MariaDB 10.4+, and HTTPS. `ffmpeg` is optional; it lets people upload any phone video. See the [install guide](docs/INSTALL.md) for an Ubuntu one-command setup and for shared hosting.

## Privacy and security

- **Photobooth photos go to OpenAI** to be restyled. Check that's OK under your company's policy; the booth screen says so.
- **The AI key never reaches the browser.** It's saved encrypted (`storage/app.key` holds the encryption key) or set in `.env`. Neither is in git.
- **Admin and the photobooth live on secret links** (Admin also needs a PIN, with lockout after 5 wrong tries). Every form is protected against cross-site requests.
- **Uploads are re-encoded** (images) or converted (videos), and the uploads folder can never run code.

## Project layout

```
public/          web root: index.php, assets, .htaccess
src/             application code, views and database migrations
bin/             command-line tools: video conversion, admin access recovery
deploy/          Ubuntu setup script
storage/         uploads and encryption key (created on the server, not in git)
docs/            install guide and the original design mockup
```

## License

[GPL-3.0](LICENSE)
