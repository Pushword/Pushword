---
title: 'Authentication - OAuth, Magic Link and Password'
h1: Authentication
publishedAt: '2025-01-22 12:00'
toc: true
---

Pushword provides a flexible authentication system with multiple login methods: password, magic link (passwordless), and OAuth (supports 60+ providers via [KnpUOAuth2ClientBundle](https://github.com/knpuniversity/oauth2-client-bundle)).

## Login Flow

The login page offers a two-step flow:

1. **Step 1**: User enters their email
2. **Step 2**: Depending on user configuration:
   - If user has a password → password form
   - If user has no password → magic link sent by email

Additionally, if OAuth is configured, provider buttons appear on the login page.

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

## OAuth (Any Provider) {id=oauth}

Enable social login with any OAuth provider supported by [KnpUOAuth2ClientBundle](https://github.com/knpuniversity/oauth2-client-bundle) (60+ providers including Google, Microsoft, GitHub, Facebook, etc.).

### Installation

1. Install the OAuth bundle and your desired provider(s):

```bash
# Core bundle (required)
composer require knpuniversity/oauth2-client-bundle

# Add providers you need
composer require league/oauth2-google        # Google
composer require thenetworg/oauth2-azure     # Microsoft/Azure
composer require league/oauth2-github        # GitHub
composer require league/oauth2-facebook      # Facebook
# See full list: https://github.com/thephpleague/oauth2-client/blob/master/docs/providers/thirdparty.md
```

2. Create `config/packages/knpu_oauth2_client.yaml` to configure your providers:

```yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(OAUTH_GOOGLE_CLIENT_ID)%'
            client_secret: '%env(OAUTH_GOOGLE_CLIENT_SECRET)%'
            redirect_route: pushword_oauth_check
            redirect_params: { provider: google }
            access_type: online
```

OAuth buttons automatically appear on the login page for each configured provider.

### Requirements

Only users **already defined** in `users.yaml` (or created in admin) can login via OAuth. If the OAuth email doesn't match an existing user, login is refused. OAuth won't create new users automatically.

### Provider Examples

#### Google {id=google-oauth}

1. Go to [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create a new project (or select existing)
3. Go to **APIs & Services** → **Credentials**
4. Click **Create Credentials** → **OAuth client ID**
5. Select **Web application**
6. Add authorized redirect URI: `https://your-domain.com/login/oauth/google/check`
7. Copy the **Client ID** and **Client Secret**

Configuration:

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        google:
            type: google
            client_id: '%env(OAUTH_GOOGLE_CLIENT_ID)%'
            client_secret: '%env(OAUTH_GOOGLE_CLIENT_SECRET)%'
            redirect_route: pushword_oauth_check
            redirect_params: { provider: google }
            access_type: online
            # Optional: restrict to Google Workspace domain
            hosted_domain: '%env(default::OAUTH_GOOGLE_HOSTED_DOMAIN)%'
```

#### Microsoft/Azure {id=microsoft-oauth}

1. Go to [Azure Portal - App registrations](https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps/ApplicationsListBlade)
2. Click **New registration**
3. Enter a name and select account types:
   - **Single tenant**: Only your organization
   - **Multitenant**: Any Azure AD directory
   - **Personal accounts**: Include personal Microsoft accounts
4. Add redirect URI: `https://your-domain.com/login/oauth/microsoft/check`
5. Go to **Certificates & secrets** → **New client secret**
6. Copy the **Application (client) ID** and **Secret value**

Configuration:

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        microsoft:
            type: azure
            client_id: '%env(OAUTH_MICROSOFT_CLIENT_ID)%'
            client_secret: '%env(OAUTH_MICROSOFT_CLIENT_SECRET)%'
            redirect_route: pushword_oauth_check
            redirect_params: { provider: microsoft }
            # Optional: restrict to specific tenant (default: "common" for any account)
            tenant: '%env(default:oauth_microsoft_tenant_default:OAUTH_MICROSOFT_TENANT)%'

parameters:
    oauth_microsoft_tenant_default: common
```

#### GitHub {id=github-oauth}

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **New OAuth App**
3. Set Authorization callback URL: `https://your-domain.com/login/oauth/github/check`
4. Copy the **Client ID** and generate a **Client Secret**

Configuration:

```yaml
# config/packages/knpu_oauth2_client.yaml
knpu_oauth2_client:
    clients:
        github:
            type: github
            client_id: '%env(OAUTH_GITHUB_CLIENT_ID)%'
            client_secret: '%env(OAUTH_GITHUB_CLIENT_SECRET)%'
            redirect_route: pushword_oauth_check
            redirect_params: { provider: github }
```

### Environment Variables

Add your OAuth credentials to `.env.local`:

```bash
# Google
OAUTH_GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
OAUTH_GOOGLE_CLIENT_SECRET=your-client-secret

# Microsoft
OAUTH_MICROSOFT_CLIENT_ID=your-azure-client-id
OAUTH_MICROSOFT_CLIENT_SECRET=your-azure-client-secret

# GitHub
OAUTH_GITHUB_CLIENT_ID=your-github-client-id
OAUTH_GITHUB_CLIENT_SECRET=your-github-client-secret
```

### Testing Locally

For local development, use `https://localhost` or `https://127.0.0.1` as your redirect URI in the OAuth provider console. You may need to run your Symfony server with HTTPS:

```bash
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

The OAuth provider didn't return an email. Ensure your OAuth app has the `email` scope permission configured in the provider's console.

### OAuth buttons don't appear

1. Verify `knpuniversity/oauth2-client-bundle` is installed
2. Check your `config/packages/knpu_oauth2_client.yaml` configuration exists and is valid
3. Clear cache: `php bin/console cache:clear`

### Magic link email not received

1. Check spam folder
2. Verify mailer is configured in `.env`:
   ```bash
   MAILER_DSN=smtp://user:pass@smtp.example.com:587
   ```
3. Check Symfony logs for mailer errors