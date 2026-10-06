---
title: 'Authentication - OAuth, Magic Link and Password'
h1: Authentication
publishedAt: '2025-01-22 12:00'
toc: true
---

Three login methods: password, magic link (passwordless) and OAuth (60+ providers via [KnpUOAuth2ClientBundle](https://github.com/knpuniversity/oauth2-client-bundle)).

## Login Flow

1. The user enters their email.
2. The password form is shown. If the account exists, a [magic link](#magic-link) is emailed at the same time, whether or not it has a password.

The response is identical for unknown addresses, so the form does not reveal which accounts exist. If OAuth is configured, provider buttons also appear on the login page.

## User Management with Flat Files {id=users-yaml}

Users can be defined in `config/users.yaml` and synced to the database by the [flat extension](/extension/flat#user-sync), to version-control who has access.

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

```bash
php bin/console pw:flat:user-sync   # users only
php bin/console pw:flat:sync        # pages, media and users
```

- **The file is the source of truth**: database users missing from it are deleted.
- New users are created **without password** (they log in by magic link or OAuth).
- Existing users get their roles, locale and username updated; **passwords are never synced**.
- Without `config/users.yaml`, user sync is skipped and no user is touched.

## Magic Link (Passwordless) {id=magic-link}

Step 1 of the login form emails every existing account two links:

- **Login link**: one-click login
- **Set password link**: sets a password for future logins

Their tokens are:

- **Hashed** (SHA-256) in database
- **Single-use** (marked as used after consumption)
- **Time-limited** (1 hour TTL)
- **Invalidated** when a new magic link is requested

## OAuth (Any Provider) {id=oauth}

### Installation

1. Install the bundle and the providers you need:

```bash
composer require knpuniversity/oauth2-client-bundle

composer require league/oauth2-google        # Google
composer require thenetworg/oauth2-azure     # Microsoft/Azure
composer require league/oauth2-github        # GitHub
composer require league/oauth2-facebook      # Facebook
# Full list: https://github.com/thephpleague/oauth2-client/blob/master/docs/providers/thirdparty.md
```

2. Declare each provider in `config/packages/knpu_oauth2_client.yaml` with `redirect_route: pushword_oauth_check` and `redirect_params: { provider: <name> }` — see the [provider examples](#provider-examples) below. A login button appears for each configured provider.

OAuth never creates users: only accounts that already exist (from `users.yaml` or the admin) can log in, and an unknown email is refused.

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

## Log In as Another User {id=impersonation}

A super administrator can browse the admin as any account that reaches `ROLE_EDITOR`, from **Log in as** in the row menu of the user list. An amber banner then stays at the top of every admin page until its **Back to my account** button returns you to the user list under your own session.

Every change made meanwhile is saved under the borrowed account: a page's `editedBy` names that editor, not you.

This is Symfony's [`switch_user`](https://symfony.com/doc/current/security/impersonating_user.html): `?_switch_user=<email>` starts it and `?_switch_user=_exit` ends it, on any URL. Only `ROLE_ALLOWED_TO_SWITCH` may switch, and `ROLE_SUPER_ADMIN` is the only role that inherits it.

## Security Best Practices

1. **Never commit OAuth secrets** to version control. Use `.env.local` or environment variables.

2. **Restrict OAuth domains** when possible:
   - Google: Use `OAUTH_GOOGLE_HOSTED_DOMAIN` to limit to your organization
   - Microsoft: Use a specific `OAUTH_MICROSOFT_TENANT` instead of "common"

3. **Use HTTPS** in production. OAuth providers require HTTPS for redirect URIs (except localhost for testing).

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