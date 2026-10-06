<?php
declare(strict_types=1);

/** First run: company name, time zone and admin PIN. Only reachable before setup is done. */
function page_setup(): void
{
    $errors = [];
    $form = ['company_name' => '', 'timezone' => 'America/Chicago'];

    if (is_post()) {
        $form['company_name'] = post_str('company_name', 80);
        $form['timezone'] = post_str('timezone', 64);
        $pin = (string) ($_POST['pin'] ?? '');

        if ($form['company_name'] === '') {
            $errors['company_name'] = 'Enter your company name.';
        }
        if (!in_array($form['timezone'], DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'] = 'Choose your time zone.';
        }
        if (!valid_pin($pin)) {
            $errors['pin'] = 'Use 4 to 12 digits for the PIN.';
        } elseif ($pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            $errors['pin_confirm'] = 'The two PINs do not match.';
        }

        if (!$errors) {
            set_setting('company_name', $form['company_name']);
            set_setting('timezone', $form['timezone']);
            set_setting('brand_color', '#2563EB');
            set_setting('use_brand_color', '0');
            set_setting('admin_pin_hash', password_hash($pin, PASSWORD_DEFAULT));
            set_setting('admin_token', new_admin_token());
            admin_sign_in();
            $_SESSION['just_installed'] = true;
            redirect(admin_base());
        }
    }

    view('setup', ['title' => 'Set up Office Contest', 'errors' => $errors, 'form' => $form], 'layout_plain');
}
