# CCI Blog support

## Choose the right support channel

We are happy to help if a step is unclear or something is not working as expected. You can describe the situation in your own words.

For a usage question or a problem you can describe publicly, [open a GitHub issue](https://github.com/cool-cat-ideas/cci-blog/issues/new). Sign in to GitHub, add a short title and describe what you tried and what happened. Questions are welcome; you do not need to confirm a bug first. You can read and reply to our messages in the same issue.

For a private question, order or licence issue, use [Services and support](https://coolcatideas.com/en/account/services/) in your Cool Cat Ideas account:

1. Sign in, or create an account if you do not have one.
2. Under **Contact the team**, choose **General question**. If your licence includes active technical support, you can instead choose **Technical support for a licence** and select the relevant **Licence**.
3. Enter a subject and describe the issue. A screenshot and the installed product version can help. Please leave out passwords, licence keys and customer data.
4. Click **Send message**. Your conversation opens after submission, and you can return to it under **Your conversations** on the same page.

You can send a general question without an active support licence. For an installation or technical intervention you already purchased, open the item under **Purchased services** and use its linked conversation.

For an unclear documentation step or a broken link, use **Report an issue** on the [online documentation page](https://coolcatideas.com/en/docs/cci-blog/1.0.0/support/). It opens the documentation feedback form. To report a security vulnerability, please [email info@coolcatideas.com](mailto:info@coolcatideas.com) rather than posting the details publicly.

Never include passwords, license keys, access tokens, customer data or production database exports in a public post.

## License and domain

Free works without a license. Pro requires `cci_blog_pro` and an active license for the shop domain.

If activation fails, send the displayed error and affected shop domain through
the private account form above. You do not need to send your licence key or
troubleshoot the connection yourself.

## Production domain checklist

CCI Blog does not connect the domain. Domain settings belong to PrestaShop and hosting.

Before launch:

1. Point DNS records to the production server.
2. Set the shop domain and SSL domain in PrestaShop.
3. Enable SSL on all pages.
4. Clear PrestaShop cache.
5. Rebuild friendly URLs if the shop was moved from staging.
6. Open the blog index, category page, article page and RSS feed on the final domain.
7. Check links saved inside article blocks, product blocks and menu blocks.

## Information to collect

Start with what you tried and what happened. The following details can help;
include what you know, and we can ask for anything else later:

- PrestaShop version,
- PHP version,
- `cci_blog` version,
- `cci_blog_pro` version if installed,
- shop domain,
- whether the shop is production, staging or local,
- active theme,
- URL of the affected article or category page,
- screenshots for desktop and mobile if the issue is visual,
- last license error code if the issue is Pro activation.

## Common checks

- Confirm `moduleRoutes` is registered if blog URLs return 404.
- Clear PrestaShop cache after changing the base slug.
- Confirm `actionFrontControllerSetMedia` loads `views/css/cci_blog_front.css` and `views/js/front.js`.
- Confirm Pro-only blocks are not saved without a valid Pro license.
- Confirm theme overrides are in `themes/{theme}/modules/cci_blog/...`.

## Support services

Installation and technical intervention are optional paid services for work on
your site. They are separate from licence support. If you have already bought
one, open it under **Purchased services** on the account page above to continue
the conversation.
