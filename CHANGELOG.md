# Changelog

This changelog documents only the two most recent **published** GitHub releases, using the previous published release for comparison. It does not document later changes on `master`.

**Important release-history note:** GitHub reports that tags `1.0.6` and `1.0.7` have **diverged histories**, rather than a linear ancestor-to-descendant relationship (`1.0.6...1.0.7`: 2 commits ahead and 3 behind). The GitHub compare view uses the merge base for its displayed changes; therefore, changes described below for `1.0.7` also account for verified differences between the two release snapshots. Do not assume that `1.0.7` contains every change made in `1.0.6`.

## 1.0.7 - 2026-10-23

### Changed

- Updated the distributed AMD JavaScript files `amd/build/main.min.js`, `amd/build/player.min.js`, and `amd/build/report.min.js`. The corresponding `amd/src/main.js`, `amd/src/player.js`, and `amd/src/report.js` are identical in the `1.0.6` and `1.0.7` release snapshots; these rebuilt outputs are not evidence of new video-annotation features.
- Updated the plugin release identifier to `1.0.7` and the build number to `2026102300` (from `1.0.6` / `2026101501` in the previous published release snapshot).
- **Release snapshot difference due to divergent histories:** the `1.0.7` tag contains `pix/icon.svg`, while the `1.0.6` tag contains `pix/icon.png` and `pix/monologo.svg`. The PNG and monologo SVG from `1.0.6` are absent from `1.0.7`. This is a difference between the published tags, not a verified linear revert commit.

[Compare published tags 1.0.6...1.0.7](https://github.com/EduardoKrausME/moodle-mod_videoannotation/compare/1.0.6...1.0.7)

## 1.0.6 - 2026-10-15

### Changed

- Added the plugin icon image at `pix/icon.png` and reorganized the former `pix/icon.svg` as `pix/monologo.svg`.
- Updated the plugin release identifier from `1.0.5` to `1.0.6` and its build number from `2026101500` to `2026101501`.

No functional changes to the Video Annotation activity were identified between the published `1.0.5` and `1.0.6` tags.

[Compare 1.0.5...1.0.6](https://github.com/EduardoKrausME/moodle-mod_videoannotation/compare/1.0.5...1.0.6)
