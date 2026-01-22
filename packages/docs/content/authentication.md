---
title: 'Authentication - OAuth, Magic Link and Password'
h1: Authentication
publishedAt: '2025-01-22 12:00'
toc: true
---

Pushword provides a flexible authentication system with multiple login methods: password, magic link (passwordless), and OAuth (Google/Microsoft).

## Login Flow

The login page offers a two-step flow:

1. **Step 1**: User enters their email
2. **Step 2**: Depending on user configuration:
   - If user has a password → password form
   - If user has no password → magic link sent by email

Additionally, if OAuth is configured, Google and/or Microsoft buttons appear on the login page.

## User Management with Flat Files {id=users-yaml}

Users can be defined in `config/users.yaml` and synced to the database (via [flat extension](/extension/flat)). This is useful for version-controlled user management or clone instance.

### Configuration

Create `config/users.yaml`:

```yaml
users:
  - email: admin@example.com
    roles: [ROLE_SUPER_ADMIN]
    locale: en
    username: Admin

  - email: editor@example.com
    roles: [ROLE_EDITOR]
    locale: fr
    username: Editor
```

### Sync Users

```bash
# Sync users from config/users.yaml to database
php bin/console pw:flat:user-sync

# Or use the global flat sync (includes users if configured)
php bin/console pw:flat:sync
```

**Important behaviors:**

- Users are created **without password** (they use magic link or OAuth to login)
- Existing users are updated (roles, locale, username)
- **Passwords are never synced** - they stay in the database only
- If `users.yaml` doesn't exist, a template file is created automatically

## Magic Link (Passwordless) {id=magic-link}

Users without a password receive a magic link email when they try to login. The email contains:

- **Login link**: One-click login (expires in 1 hour)
- **Set password link**: Allows setting a password for future logins

### How it works

1. User enters email on login page
2. System detects user has no password
3. Email is sent with two secure, single-use tokens
4. User clicks either link to authenticate

Tokens are:

- **Hashed** (SHA-256) in database
- **Single-use** (marked as used after consumption)
- **Time-limited** (1 hour TTL)
- **Invalidated** when a new magic link is requested

## OAuth (Google & Microsoft) {id=oauth}

Enable social login with Google and/or Microsoft accounts.

### Installation

Install the required OAuth packages:

```bash
composer require knpuniversity/oauth2-client-bundle league/oauth2-google thenetworg/oauth2-azure
```

Then register the bundle in `config/bundles.php`:

```php
return [
    // ... other bundles
    KnpU\OAuth2ClientBundle\KnpUOAuth2ClientBundle::class => ['all' => true],
];
```

### Requirements

Only users **already defined** in `users.yaml` (or created in admin) can login via OAuth. If the OAuth email doesn't match an existing user, login is refused.

### Google OAuth Setup {id=google-oauth}

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a new project (or select existing)
3. Go to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth client ID**
5. Select **Web application**
6. Add authorized redirect URI:
   ```
   https://your-domain.com/login/oauth/google/check
   ```
7. Copy the **Client ID** and **Client Secret**

### Microsoft OAuth Setup {id=microsoft-oauth}

1. Go to [Azure Portal - App registrations](https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
2. Click **New registration**
3. Enter a name and select account types:
   - **Single tenant**: Only your organization
   - **Multitenant**: Any Azure AD directory
   - **Personal accounts**: Include personal Microsoft accounts
4. Add redirect URI:
   ```
   https://your-domain.com/login/oauth/microsoft/check
   ```
5. Go to **Certificates & secrets** → **New client secret**
6. Copy the **Application (client) ID** and **Secret value**

### Environment Configuration

Add to your `.env` or `.env.local`:

```bash
# Google OAuth
OAUTH_GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
OAUTH_GOOGLE_CLIENT_SECRET=your-google-client-secret

# Optional: Restrict to a specific Google Workspace domain
OAUTH_GOOGLE_HOSTED_DOMAIN=your-company.com

# Microsoft OAuth
OAUTH_MICROSOFT_CLIENT_ID=your-azure-client-id
OAUTH_MICROSOFT_CLIENT_SECRET=your-azure-client-secret

# Optional: Restrict to a specific tenant (default: "common" for any account)
OAUTH_MICROSOFT_TENANT=your-tenant-id
```

### Enabling OAuth

OAuth buttons automatically appear on the login page when the corresponding `CLIENT_ID` environment variable is set and non-empty.

| Variable                        | Effect                                 |
| ------------------------------- | -------------------------------------- |
| `OAUTH_GOOGLE_CLIENT_ID` set    | Google button appears                  |
| `OAUTH_MICROSOFT_CLIENT_ID` set | Microsoft button appears               |
| Both set                        | Both buttons appear                    |
| Neither set                     | No OAuth buttons (email/password only) |

### Testing Locally

For local development, use `https://localhost` or `https://127.0.0.1` as your redirect URI in the OAuth provider console. You may need to run your Symfony server with HTTPS:

```bash
symfony server:start --allow-http
# or with built-in SSL
symfony server:ca:install
symfony server:start
```

## Security Best Practices

1. **Never commit OAuth secrets** to version control. Use `.env.local` or environment variables.

2. **Restrict OAuth domains** when possible:
   - Google: Use `OAUTH_GOOGLE_HOSTED_DOMAIN` to limit to your organization
   - Microsoft: Use a specific `OAUTH_MICROSOFT_TENANT` instead of "common"

3. **Define users in `users.yaml`** to control who can access the admin. OAuth won't create new users automatically.

4. **Use HTTPS** in production. OAuth providers require HTTPS for redirect URIs (except localhost for testing).

## Troubleshooting

### "No account found with this email"

The OAuth email doesn't match any user in the database. Add the user to `config/users.yaml` and run:

```bash
php bin/console pw:flat:user-sync
```

### "Could not retrieve email from OAuth provider"

The OAuth provider didn't return an email. For Microsoft, ensure your app has the `email` scope permission in Azure Portal.

### OAuth buttons don't appear

Check that environment variables are set correctly:

```bash
php bin/console debug:container --env-vars | grep OAUTH
```

### Magic link email not received

1. Check spam folder
2. Verify mailer is configured in `.env`:
   ```bash
   MAILER_DSN=smtp://user:pass@smtp.example.com:587
   ```
3. Check Symfony logs for mailer errors
