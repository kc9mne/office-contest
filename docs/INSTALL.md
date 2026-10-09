# Installing Office Contest

Office Contest is a plain PHP and MySQL website. There's no build step and nothing to compile. There are two ways to run it:

| | **Ubuntu server** (recommended) | **Shared hosting** (cPanel and similar) |
|---|---|---|
| Setup | One script does everything | Upload files, create a database by hand |
| Video uploads | Any phone video, converted to MP4 automatically | MP4 and WebM only (no converter) |
| Upload size | 300 MB | Whatever your host allows |
| HTTPS certificate | Free, set up automatically | Usually included by the host |
| Photobooth AI | Yes | Yes |

**HTTPS is required.** Browsers only let a web page use a camera on a secure (`https://`) address, so the photobooth won't work without it.

---

## Option A: Ubuntu server

### What you need

- **A server:** Ubuntu 24.04 or newer (we test on 26.04), with 2 CPU cores, 4 GB of memory, and 40 GB or more of disk if people will upload videos.
- **A domain name:** for example `officevote.example.com`, pointing at the server (a DNS **A record** with the server's public IP address).
- **Open ports:** 80 and 443 reachable from the internet. The certificate check needs port 80.
- **Access:** a user that can run `sudo`.

### 1. Run the setup script

Log in to the server and run:

```bash
curl -fsSLo setup-ubuntu.sh https://raw.githubusercontent.com/kc9mne/office-contest/main/deploy/setup-ubuntu.sh
```

```bash
sudo bash setup-ubuntu.sh --domain officevote.example.com
```

It takes a few minutes. The script:

- installs Apache, PHP, MariaDB and ffmpeg, with PHP set up for 300 MB uploads
- downloads the site to `/var/www/officevote`
- creates the database with a random password, saved in `/var/www/officevote/.env`
- configures Apache so uploaded files can never run as code
- gets a free HTTPS certificate from Let's Encrypt
- opens the firewall for web traffic, if the firewall is on

You can run it again safely. It won't overwrite your `.env` file or your data.

**If the certificate step fails,** the domain isn't pointing at the server yet, or port 80 is blocked. Fix that, then run:

```bash
sudo certbot --apache -d officevote.example.com
```

### Office-only server (not reachable from the internet)

If photos should stay inside the building, run the site on a small server on the office network instead. Let's Encrypt can still give it a trusted certificate by checking a DNS record instead of connecting to the server.

1. **Point the name at the office address.** At your DNS provider, create an **A record** for `officevote.example.com` pointing at the server's office address (e.g. `10.20.105.50`). Ask IT to reserve that address so it never changes.
2. **Run the setup script with `--dns-cert`:**

   ```bash
   sudo bash setup-ubuntu.sh --domain officevote.example.com --dns-cert
   ```

3. **Add the TXT record it shows you.** The script pauses with a record like `_acme-challenge.officevote` and a long code. Add it as a **TXT** record at your DNS provider (GoDaddy: **Domain → DNS → Add New Record**), wait a minute or two, then press Enter. You can delete the TXT record afterwards.

You can set the server up anywhere (at home, on your desk) and move it to the office later. Just update the A record to its office address.

- **Renewing:** the certificate lasts 90 days. To renew, run the same command again and add the new TXT record. The script checks the date and only asks when renewal is due.
- **If phones can't reach it:** some office networks block public names that point at private addresses ("DNS rebinding protection"). If that happens, ask IT to add an internal DNS entry for the name. The certificate keeps working.
- **What still leaves the building:** the photobooth sends photos to OpenAI to restyle them. Everything else stays on the server. The box needs normal outbound internet for the AI, web fonts and YouTube.

### 2. First-time setup

Open `https://officevote.example.com`. You'll get the setup page:

1. Enter your company name and time zone, and choose an admin PIN.
2. You land in Admin with your **private admin link** at the top. **Bookmark it.** You need the link and the PIN to get back in; without the link, Admin just shows "page not found".

Then, in Admin:

- **Site settings:** upload your logo, pick a brand color if you want one, and add your list of departments.
- **Site settings → Photobooth AI:** paste your OpenAI API key (see [The photobooth](#the-photobooth)).
- **Contests → New contest:** pick the type, dates and categories, then **Make live**.
- **Contests → Print QR poster:** print one for every break room.

---

## Option B: Shared hosting

### What you need

- PHP 8.2 or newer with these extensions: `pdo_mysql`, `gd`, `curl`, `mbstring`, `fileinfo`, `sodium`, `exif`. Most hosts have all of them; check in cPanel under **Select PHP Version**.
- MySQL 5.7+ or MariaDB 10.4+.
- An HTTPS certificate for the domain. Most hosts give one free (AutoSSL or Let's Encrypt).

### 1. Create the database

In cPanel → **MySQL Databases**:

1. Create a database.
2. Create a user with a strong password.
3. Add the user to the database with **All privileges**.

The site creates its own tables the first time it runs.

### 2. Upload the files

Download the code as a ZIP from GitHub (**Code → Download ZIP**) and unzip it into a folder **outside** `public_html`, for example `/home/you/office-contest`.

Then point a domain or subdomain at the **`public`** folder inside it. In cPanel → **Domains** (or **Subdomains**), create `officevote.example.com` and set its **Document root** to `office-contest/public`.

> Only the `public` folder should be reachable from the web. The rest (code, `.env` with your passwords, uploaded files) must stay outside the web root.

### 3. Create the settings file

Copy `.env.example` to `.env` in the `office-contest` folder (not in `public`). Then fill in:

```ini
APP_URL=https://officevote.example.com
DB_DSN=mysql:host=localhost;dbname=YOUR_DATABASE;charset=utf8mb4
DB_USER=YOUR_DATABASE_USER
DB_PASS=YOUR_PASSWORD
```

### 4. Make the uploads folder writable

Create `office-contest/storage/media` if it doesn't exist and make sure PHP can write to it. In cPanel's File Manager, set permissions to `755` (or `775` if uploads fail).

### 5. Finish setup

Open the site and follow [First-time setup](#2-first-time-setup) above.

### What's different on shared hosting

- **Uploads:** `public/.user.ini` asks for 300 MB uploads, but many hosts cap it lower. If big uploads fail, raise **upload_max_filesize** and **post_max_size** in cPanel → **Select PHP Version → Options**.
- **Videos:** there's no video converter, so uploads must already be MP4 or WebM. Android phones record MP4. iPhones record MOV unless **Settings → Camera → Formats → Most Compatible** is on. YouTube links always work and are the easiest choice here.
- **The photobooth** works the same. The server just needs to reach `api.openai.com` over HTTPS.

---

## The photobooth

- **Turn it on:** open the contest in Admin and switch on **Photobooth**. It's on by default for Halloween and Holiday party.
- **Add an AI key:**
  1. Create a key at [platform.openai.com](https://platform.openai.com) under **API keys**, and make sure the account has billing set up.
  2. Paste it in **Admin → Site settings → Photobooth AI**. It's checked before it's saved and stored encrypted.
  3. Without a key the booth runs in **demo mode** (a color filter instead of AI), which is handy for trying it out.
- **Control costs:** each photo is one AI request. Set a **Daily AI photo limit** on the contest page, and choose a lower quality in Photobooth AI if you want it cheaper.
- **Set up the booth device:** open the booth link from **Site settings → Photobooth** on the tablet or PC and allow the camera. Tap the full-screen button. If there's more than one camera, pick one with the camera button; the choice is remembered on that device.
- **Placement:** put the camera at head height in front of a plain wall with good light. Mark a spot on the floor about 4–6 feet away.

---

## Moving from a test server to the real one

Usually it's simplest to install fresh on the real server and set things up again in Admin.

To bring everything across (contests, entries, votes, photos, settings), run this on the **old** server:

```bash
sudo mysqldump officevote > officevote.sql
```

```bash
sudo tar -czf officevote-storage.tgz -C /var/www/officevote storage
```

Copy both files to the new server (after its setup script has run), then:

```bash
sudo mysql officevote < officevote.sql
```

```bash
sudo tar -xzf officevote-storage.tgz -C /var/www/officevote && sudo chown -R www-data:www-data /var/www/officevote/storage
```

`storage/` holds the uploaded photos and videos, plus `app.key`, which decrypts the saved OpenAI key. If you don't copy `app.key`, paste the OpenAI key again in Admin.

Afterwards, update `APP_URL` in the new server's `.env` to the new address. The admin link and booth link stay the same; only the domain part changes.

---

## Updating

On Ubuntu:

```bash
sudo git -C /var/www/officevote pull
```

On shared hosting, upload the new files over the old ones. Keep your `.env` and `storage` folder.

Database changes apply automatically on the next page load. Take a backup first (below).

---

## Backups

Everything lives in two places: the **database** and the **`storage` folder**.

```bash
sudo mysqldump officevote > ~/officevote-$(date +%F).sql
```

```bash
sudo tar -czf ~/officevote-storage-$(date +%F).tgz -C /var/www/officevote storage
```

During a contest, a nightly copy is plenty. Before **Reset votes** or deleting a contest, download the results from the contest page as well.

---

## Before the event

- [ ] Contest is **live** (Admin → Contests shows "On home page") with the right start and end times
- [ ] Logo, brand color and departments set
- [ ] QR posters printed and up
- [ ] Booth tablet or PC set up, camera allowed, full screen, AI key working (**Test the key** in Site settings)
- [ ] Daily AI photo limit set
- [ ] You've entered and voted once from your own phone
- [ ] Admin link bookmarked on a second device

On the day, keep **Admin → Voters → Worth a look** open to catch duplicate votes, and **Admin → Entries** if approval is on.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| **"The site cannot reach its database"** | Check `DB_DSN`, `DB_USER` and `DB_PASS` in `.env`. On Ubuntu: `sudo cat /var/www/officevote/.env`. |
| **Photobooth says the camera isn't available** | The page must be on `https://`. Allow the camera in the browser (camera icon in the address bar). Close other apps using the camera. |
| **Booth says the AI account is out of credits** | Add billing or credits at platform.openai.com, then tap **Try again**. |
| **Big photo or video uploads fail** | The host's upload limit is lower than the file. Raise `upload_max_filesize` and `post_max_size`, or use a YouTube link. |
| **A video is stuck on "Converting"** | Run it by hand to see the error: `sudo -u www-data php /var/www/officevote/bin/process-video.php VIDEO_ID` (the ID is in the database, `videos` table). Check `ffmpeg -version` works. |
| **Lost the admin link** | `sudo -u www-data php /var/www/officevote/bin/admin-access.php` prints it. |
| **Forgot the admin PIN, or locked out by wrong PINs** | `sudo -u www-data php /var/www/officevote/bin/admin-access.php --new-pin 2468` (use your own digits). It also clears the lockout. |
| **Admin link was shared by mistake** | Admin → Site settings → **Make a new admin link**, or `bin/admin-access.php --new-link`. |
| **Votes flagged "same browser and network" for everyone** | Normal when everyone's on the office Wi-Fi: they share one network address. Focus on the red **same name** flags. |
| **Hyper-V test VM is very slow** | Turn off **Dynamic Memory** in the VM's settings and give it a fixed 4 GB. |
| **`apt` hangs on a Hyper-V test VM** | The Default Switch doesn't carry IPv6: `echo 'Acquire::ForceIPv4 "true";' \| sudo tee /etc/apt/apt.conf.d/99force-ipv4` |
| **Error log fills with "PCRE JIT" warnings** | Fixed by the setup script (`pcre.jit = 0`). Re-run it, or add that line to PHP's settings. |

Error details are logged, not shown to visitors. On Ubuntu they're in `/var/log/apache2/officevote-error.log`.
