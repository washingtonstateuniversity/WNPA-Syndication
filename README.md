# Publish Feed Items

[![Build Status](https://travis-ci.org/washingtonstateuniversity/WSUWP-Plugin-Publish-Feed-Items.svg?branch=master)](https://travis-ci.org/washingtonstateuniversity/WSUWP-Plugin-Publish-Feed-Items)

Publish Feed Items is a [WordPress](http://wordpress.org) plugin used to manage and republish content from multiple external RSS feeds.

## Overview

The primary objective for Publish Feed Items is to ease the sharing of aggregated stories related to a site.

This plugin offers a central area for content from multiple external sources to be stored. Content is republished through a single feed that can be used to augment local news collections.

## External Sources

External sources can be any standard [RSS](http://cyber.law.harvard.edu/rss/rss.html) feed. At [WNPA](http://www.wnpa.com/), these are the feeds of weekly newspapers throughout the state of Washington.

External sources are added and managed through the corresponding menu option in WordPress.

1. Go to **External Sources** under **Add New** in the left menu of the admin area.
    * This can also be accessed via **New** in the top toolbar.
1. Enter a valid RSS feed URL in **Feed URL**
    * e.g. `http://news.wsu.edu/feed/`
1. Save the source. Information indicating success or failure of item retrieval will be displayed.
    * Click **Save Draft** if you would like to save the source without retrieving items yet.
    * Click **Publish** or **Update** if the items from this source should be published.

### Hourly Updates

Once an external source is added, it will be processed on an hourly schedule for new feed items.

### Additional Feed Attributes

While any standard RSS feed can be used, specific item elements in the feed can help categorize content and assign visibility.

* [category](http://cyber.law.harvard.edu/rss/rss.html#ltcategorygtSubelementOfLtitemgt) Category elements with no specified domain will be used to assign general tags to feed items.
* [category domain="wnpalocation"](http://cyber.law.harvard.edu/rss/rss.html#ltcategorygtSubelementOfLtitemgt)  Category elements with the `wnpalocation` domain assigned will be recognized under a location taxonomy.
* [dc:accessRights](http://purl.org/dc/terms/accessRights) (Default `public`) Can be set to either `public` or `private` in a feed to indicate the visibility of an item to the general public.

These same item elements are provided in the RSS feeds produced by WNPA Syndication.

#### Example item in an RSS feed

```
<item>
    <title>WSU partners with sustainable, quality food providers</title>
    <link>http://news.wsu.edu/2014/02/10/wsu-partners-with-sustainable-quality-food-providers/</link>
    <pubDate>Mon, 10 Feb 2014 12:00:00 +0000</pubDate>
    <category><![CDATA[dining services]]></category>
    <category><![CDATA[food]]></category>
    <category><![CDATA[local]]></category>
    <category><![CDATA[healthy]]></category>
    <category domain="wnpalocation"><![CDATA[Pullman]]></category>
    <guid isPermaLink="false">http://news.wsu.edu/2014/02/10/wsu-partners-with-sustainable-quality-food-providers/</guid>
    <description><![CDATA[PULLMAN, Wash. – Dining Services supports the community by building a strong base of local and regional suppliers to provide quality food responsibly grown.]]></description>
    <content:encoded><![CDATA[<p>Story content formatted in HTML</p>]]></content:encoded>
    <dc:accessRights>public</dc:accessRights>
</item>
```

## Feed Items

Feed items are the individual articles pulled in through the external source RSS feed and can be accessed under the **Feed Items** menu in WordPress.

### Central Feed

Once items have been pulled in from external sources, they are immediately republished through a central feed.

* If the permalink option in **General** under **Settings** in the left menu of the admin area is set to anything other than *Default*, the URL of the feed will follow the structure of `http://wnpa.wsu.edu/feed-items/feed/`.
* If the permalink option is left at its default setting the URL will follow the structure of `http://wnpa.wsu.edu/?post_type=wnpa_feed_item&feed=rss2`.

Only feed items that have a visibility of `public` will be shown in this central feed. To see items in a feed that have been marked as `private`, an access key is required.

## Access Keys

Access keys are used in combination with the central feed to gain access to items that have been marked as private. Each user has the ability to generate their own access key through their profile.

1. Go to **Your Profile** under **Users** in the left menu of the admin area.
1. Scroll to the bottom of your profile page. **WNPA Access Information** should be listed.
1. Click **Generate** to populate the field for **Access Key**.
1. Click **Update Profile** at the bottom.
1. Copy the generated **Feed URL** that now has an appended access key.
    * e.g. `http://wnpa.wsu.edu/feed-items/feed/?access_key=154379977f67e13d26c77fd519ce5124`
