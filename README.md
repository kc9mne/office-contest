# Office Contest

A small website for running office contests: costume contests, ugly sweater showdowns, chili cook-offs, cookie bake-offs and anything else people vote on. Coworkers scan a QR code, browse the entries on their phones and vote. No accounts or app installs needed.

> **Status:** planning. A clickable mockup is in [`docs/mockup.html`](docs/mockup.html). Open it in a browser to try every screen.

## Features (planned)

### For everyone
- **Home page**: countdown to the start or end of voting, the overall top 3, and the current leader in each category.
- **Voting**: no logins. Voters type their name once, and each device gets one vote per category. Picks can be changed until voting closes.
- **Join**: enter yourself with a photo (taken right on your phone), your name, department and entry name.
- **Gallery**: contest entries, photobooth pictures and videos in one place.
- **Videos**: upload a phone video (up to 300 MB, stored on our own server) or paste a YouTube link.

### Contest modes
| Mode | Example | Starter categories | Photobooth |
|---|---|---|---|
| Halloween | Costume Contest | Scariest, Funniest, Most Creative | On: Zombie, Vampire, Haunted portrait |
| Holiday party | Ugly Sweater Showdown | Ugliest, Most Festive, Best DIY | On: Elf, Snow globe, Ugly sweater |
| General | Chili Cook-Off, cookie contest | Spiciest, Best Flavor, Most Original | Off by default |

Each mode changes the colors, the wording (costume, sweater, dish), the starter categories and the event name. All modes run on the same pages and the same code.

### AI photobooth
A separate full-screen page for a tablet or an office PC with a webcam, set up in front of a plain wall:

1. Tap **I'm ready**, then a 3-second countdown takes the photo.
2. Preview it, then **Retake** or **Looks good**.
3. Pick a look and optionally add your name.
4. The server sends the photo to an AI image service and gets back a restyled version.
5. See the before and after, add it to the gallery, and scan a QR code to save it to your phone.

### Admin
Admins open the admin page with a secret link plus a PIN. From there they can:
- Set the company logo, company name and an optional brand color that replaces the mode colors.
- Create contests and see past contests in an archive.
- Set the mode, title, start and end times, and categories.
- Turn options on or off: photo approval (off by default), live vote counts, the photobooth.
- Edit the AI instructions for each photobooth look, and set a daily photo limit.
- Review the voter list. Likely duplicates are flagged (the same name on two devices, a burst of new devices on one network, or only a first name) and a vote can be voided with one tap.
- Close voting early, reset votes, remove entries, print the QR poster, and export results.

## Rules decided so far
- One vote per device per category. The voter's name is required.
- The overall winner is the total of an entry's votes across all categories.
- One entry can win several categories, and ties share a place.
- Photo approval is available but off by default.

## Security notes
- **The AI API key stays on the server.** Add it in Admin → Site settings → Photobooth AI, where it's stored encrypted (the encryption key is `storage/app.key`, outside the web root), or put it in `.env`. Neither file is in git. Back up `storage/app.key` with your database, or you'll need to re-enter the key after a restore.
- **Protect the photobooth page** with its own PIN or secret link, plus the daily photo limit, so strangers can't run up the AI bill.
- **Photos are sent to an outside AI service.** Check this is OK under your company's policy. The booth screen shows a notice.

## Open questions
- Can people vote for their own entry?
- Who can post videos: anyone, or only admins?
- Use a personal or a company AI account?

## License
[GPL-3.0](LICENSE)
