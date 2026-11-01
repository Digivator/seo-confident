# Releasing

How a new version of SEO Confident goes out.

## 1. Prepare the release

1. Update the version in all four places:
   - `Version:` in the header of `seo-confident.php`
   - the `SEOC_VERSION` constant in `seo-confident.php`
   - `Stable tag:` in `readme.txt`
   - a new entry at the top of the changelog in `readme.txt`, written as `= x.y.z =`
2. Move the **Unreleased** notes in `CHANGELOG.md` under a new `## [x.y.z] - YYYY-MM-DD` heading.
3. Check that **Tested up to** in `readme.txt` matches the current WordPress release.
4. Run the checks:

   ```bash
   composer phpcs
   bash bin/check-version.sh
   ```

5. Commit the changes and merge them into `main` once CI is green.

## 2. Tag the release

```bash
git tag v1.0.0
git push origin v1.0.0
```

Pushing a tag starts the **Release** workflow. It confirms the tag matches the plugin version, builds `seo-confident-x.y.z.zip`, takes the release notes from `CHANGELOG.md` and publishes a GitHub release with the zip attached.

To build the zip yourself, commit your changes and run `bash bin/build-zip.sh`. The file appears in `dist/`.

## 3. WordPress.org

**First submission.** Upload the zip on the [Add your plugin](https://wordpress.org/plugins/developers/add/) page and wait for the review team's reply. The plugin must pass the [detailed plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). CI runs Plugin Check, which catches most of what the reviewers look for.

**After approval.** The plugin gets its own Subversion repository. Copy the contents of the zip into `trunk`, then tag the release:

```bash
svn checkout https://plugins.svn.wordpress.org/seo-confident svn-seo-confident
cd svn-seo-confident
# copy the plugin files into trunk/
svn add --force trunk
svn cp trunk tags/x.y.z
svn commit -m "Release x.y.z"
```

Banners, icons and screenshots live in the repository's `.wordpress-org` folder and go in the Subversion `assets` folder. See the note in that folder for the file names and sizes.

Later on, the [10up WordPress plugin deploy action](https://github.com/10up/action-wordpress-plugin-deploy) can do this step from a tag. It reads the same `export-ignore` rules in `.gitattributes`, so the files it deploys match the release zip.

## Version numbers

The project follows [Semantic Versioning](https://semver.org/). The version must go up with every release, because WordPress only offers an update when it does.
