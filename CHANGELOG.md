# Publish Feed Items Changelog

## 1.0.0 (April 11, 2016)

* Rename plugin from WNPA Syndication to Publish Feed Items to better fit mission.
* Deprecate the `wnpa_content_type` filter. Only one unused theme was using this. Feed items can become posts through the submit meta box now.
* Introduce the `wsuwp_pfi_public_feed_items` filter. This allows opt-in public feed items.
* Introduce the `wsuwp_pfi_default_post_status` filter. This allows the default post status of draft to be modified.

## 0.3.2 (April 9, 2015)

* Fix notice when a feed does not provided categories.

## 0.3.1 (October 8, 2014)

* Add `wnpa_content_type` filter to allow an override of the default content type.
