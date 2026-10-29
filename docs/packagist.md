# Publishing `erikwang2013/bigdata-php` on Packagist

The repository is https://github.com/erikwang2013/bigdata-php.

> No Packagist credentials exist in this environment. The steps below marked
> "browser" must be performed by the maintainer with a Packagist account
> linked to GitHub.

## 1. Submit the package (browser, once)

1. Confirm the repository is public on GitHub.
2. Sign in at https://packagist.org with the GitHub account that can access
   the repository.
3. Open https://packagist.org/packages/submit.
4. Enter `https://github.com/erikwang2013/bigdata-php` and click **Check**.
5. Click **Submit**. Packagist reads `composer.json` and lists the package as
   `erikwang2013/bigdata-php`.

## 2. Configure the GitHub webhook (browser, once)

Packagist shows the webhook URL and secret on the package page after
submission.

1. On https://packagist.org/packages/erikwang2013/bigdata-php click
   **Settings** and copy the **Payload URL** (it looks like
   `https://packagist.org/api/github?username=erikwang2013`) and the
   **Secret**.
2. In GitHub, open **Settings → Webhooks → Add webhook** for the repository.
3. Fill in:
   - **Payload URL**: the URL copied from Packagist.
   - **Content type**: `application/json`.
   - **Secret**: the secret copied from Packagist.
   - **Which events?**: **Just the push event** (tag pushes are included).
4. Click **Add webhook**. GitHub sends a ping; Packagist then updates the
   package automatically on every push and tag.

If the webhook cannot be added, click **Update** on the Packagist package
page to force a refresh.

## 3. Release v1.0.1 (and every future vX.Y.Z)

CI must be green before tagging.

1. Update `CHANGELOG.md` with a `## [1.0.1] - YYYY-MM-DD` section and commit
   it on `main`.
2. Create and push the tag:
   ```sh
   git tag -a v1.0.1 -m "v1.0.1"
   git push origin v1.0.1
   ```
3. The tag push triggers `.github/workflows/release.yml`, which runs the tests
   on PHP 8.5 and creates the GitHub release
   (`gh release create v1.0.1 --title v1.0.1 --generate-notes --verify-tag`).
4. The same tag push reaches Packagist via the webhook, which publishes
   version `v1.0.1`. Users install it with:
   ```sh
   composer require erikwang2013/bigdata-php:^1.0
   ```

For every later release repeat step 1–3 with the next `vX.Y.Z` tag; the
workflow's `v*.*.*` filter matches all of them. Never move or reuse a
published tag — create a new patch version instead.
