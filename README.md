# Laravel Graph Mail

Send Laravel mail through the **Microsoft Graph API** instead of SMTP — no app passwords, no legacy auth, works with tenants that have Basic Auth / SMTP AUTH disabled (which is now most of them).

Drop-in Laravel Mail transport: keep using `Mail::to(...)->send(new YourMailable)`, Notifications, and Mailables exactly as-is — just point the `mail.default` mailer at `graph`.

[![Tests](https://github.com/YOUR-USERNAME/laravel-graph-mail/actions/workflows/tests.yml/badge.svg)](https://github.com/YOUR-USERNAME/laravel-graph-mail/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/graph-mail/laravel-graph-mail.svg)](https://packagist.org/packages/graph-mail/laravel-graph-mail)
[![License](https://img.shields.io/packagist/l/graph-mail/laravel-graph-mail.svg)](https://packagist.org/packages/graph-mail/laravel-graph-mail)

## Why

Microsoft is steadily disabling legacy SMTP AUTH across Exchange Online tenants, which breaks the classic `MAIL_MAILER=smtp` + app-password approach. The Graph API's `sendMail` endpoint with an app-only `Mail.Send` permission is the supported modern replacement — but the Azure AD setup has a few genuinely confusing failure modes that this package's troubleshooting section (and built-in test command) are designed to catch fast.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- An Azure AD tenant with permission to register an application and grant admin consent

## Installation

```bash
composer require graph-mail/laravel-graph-mail
php artisan vendor:publish --tag=graph-mail-config
```

## Azure AD Setup

1. **Register an app** — Azure Portal → App registrations → New registration. Note the **Application (client) ID** and **Directory (tenant) ID**.
2. **Create a client secret** — Certificates & secrets → New client secret. Copy the **Value** column immediately — it's only shown once. (The Secret ID, shown permanently, will *not* work as a credential — this is the single most common setup mistake.)
3. **Add the API permission** — API permissions → Add a permission → Microsoft Graph → **Application permissions** (not Delegated) → search `Mail.Send` → Add.
4. **Grant admin consent** — click "Grant admin consent for [tenant]" at the top of the API permissions page. You need Global Administrator or Privileged Role Administrator rights to do this. Confirm the Status column shows a green checkmark afterward.
5. **Pick a sender mailbox** — a licensed user mailbox or shared mailbox. Shared mailboxes (e.g. `noreply@yourdomain.com`) are recommended since they don't consume a license and are purpose-built for this.

## Configuration

`.env`:

```env
MAIL_MAILER=graph

MS_TENANT_ID=your-tenant-id
MS_CLIENT_ID=your-client-id
MS_CLIENT_SECRET=your-client-secret-value
MS_SENDER_EMAIL=noreply@yourdomain.com
```

`config/mail.php` — add the `graph` mailer:

```php
'mailers' => [
    // ...your other mailers
    'graph' => [
        'transport' => 'graph',
    ],
],
```

## Usage

Works with Laravel's standard Mail API — no code changes needed beyond your mailer config:

```php
Mail::to('user@example.com')->send(new InvoiceMailable($invoice));

Mail::to('user@example.com')->queue(new WelcomeMailable($user));

Mail::raw('Plain text body', function ($message) {
    $message->to('user@example.com')->subject('Quick note');
});
```

### Sending from a different address per-message

The transport uses the Mailable's `from()` address when set, falling back to `MS_GRAPH_SENDER`:

```php
Mail::raw('Billing question follow-up', function ($message) {
    $message->to('customer@example.com')
        ->from('billing@yourdomain.com')
        ->subject('Re: Invoice #1234');
});
```

Both addresses must be mailboxes your app is permitted to send from (see the Application Access Policy note below if your tenant restricts this).

### Attachments

```php
Mail::raw('See attached report', function ($message) {
    $message->to('user@example.com')
        ->subject('Monthly Report')
        ->attach(storage_path('app/reports/october.pdf'));
});
```

## Testing your setup

The package ships an artisan command that checks token acquisition, a direct Graph API call, and the full Laravel Mail transport in one pass:

```bash
php artisan graph-mail:test your-test-address@example.com
```

Each step is isolated in the output so you can immediately see which layer is failing.

## Troubleshooting

These are the real failure modes you're likely to hit, in the order they tend to occur:

### `invalid_client` / `AADSTS7000215: Invalid client secret provided`

You copied the **Secret ID** instead of the **Secret Value**. Azure only shows the Value once, at creation time — if you've navigated away, it's gone for good. Create a new client secret and copy the Value column this time.

### `403 ErrorAccessDenied` on the sendMail call, even though the token was issued fine

A valid token proves your client ID/secret/tenant are correct — it does **not** prove you have the `Mail.Send` permission granted. This is the most common and most confusing failure. Three possible causes, in order of likelihood:

**1. Admin consent didn't actually take, despite the portal showing a checkmark.**

Verify the *real* grant state via Graph API rather than trusting the UI:

```
GET https://graph.microsoft.com/v1.0/servicePrincipals?$filter=appId eq '{your-client-id}'
```

Copy the returned `id`, then:

```
GET https://graph.microsoft.com/v1.0/servicePrincipals/{id}/appRoleAssignments
```

If this returns an **empty array**, no application permissions were actually granted — redo the admin consent step as a Global Administrator, then re-run this check to confirm a non-empty result containing the `Mail.Send` role (`b633e1c5-b582-4048-a93e-9f11b44c7e96`).

**2. An Exchange Online Application Access Policy is scoping your app to different mailboxes.**

This is invisible from the Azure Portal entirely — it's an Exchange-side restriction, not an Azure AD one. Even with `Mail.Send` correctly granted, many tenants restrict *which* mailboxes an app can act on. Ask an Exchange admin to run:

```powershell
Connect-ExchangeOnline
Get-ApplicationAccessPolicy
```

If a policy exists and doesn't include your sender mailbox, either add the mailbox to the policy's scoped group or create a new policy:

```powershell
New-ApplicationAccessPolicy -AppId "your-client-id" `
    -PolicyScopeGroupId "sender@yourdomain.com" `
    -AccessRight RestrictAccess `
    -Description "Allow Laravel app to send as this mailbox"
```

**3. The sender mailbox isn't a valid, licensed mailbox.**

Confirm `MS_GRAPH_SENDER` points to an actual licensed user or shared mailbox — not a distribution list, security group, or unlicensed account.

### `405 Method Not Allowed` on the sendMail call

Almost always a malformed URL, not an actual verb issue — usually caused by `MS_GRAPH_SENDER` being empty, or containing quotes/trailing whitespace/newlines from a copy-paste into `.env`. Run:

```bash
php artisan tinker
>>> dump(config('graph-mail.default_sender'));
>>> dump(strlen(config('graph-mail.default_sender')));
```

If the string length looks longer than the visible text, there's hidden whitespace — clean up the `.env` value and run `php artisan config:clear`.

## Testing (for contributors)

```bash
composer install
composer test
```

Tests use `Http::fake()` throughout — no real Azure/Graph credentials or network access required.

## Security

If you discover a security vulnerability, please email the maintainer directly rather than opening a public issue.

## License

MIT. See [LICENSE](LICENSE).
