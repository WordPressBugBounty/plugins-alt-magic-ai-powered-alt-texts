=== Alt Magic: AI Powered Alt Texts & Image Renaming ===
Contributors: altmagic, advait95
Tags: image alt text, Alternative Text, alt text, image to text, ai alt text
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.6.2
Requires PHP: 7.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically generate SEO-optimized AI alt texts and rename images with AI. Improve accessibility, ranking, and WooCommerce product image visibility.

== Description ==
Alt Magic is an AI-powered WordPress plugin that automatically generates descriptive alt texts and image filenames to enhance SEO, accessibility, and discoverability. Perfect for bloggers, agencies, and e-commerce sites seeking automated SEO image optimization.

== Customer Reviews ==
⭐⭐⭐⭐⭐ **Rated 4.8 stars on G2** - [Read reviews on G2](https://www.g2.com/products/alt-magic-ai-powered-alt-texts-at-scale/reviews)

== Watch Alt Magic Plug-in in Action: ==
https://www.youtube.com/watch?v=lHqcZ2Egz4Y

== Features: ==
**Automatic Generation:** Every newly uploaded image is automatically analyzed and contextually relevant alt text is added to the image properties.

**One-Click Bulk Generation:** Generate alt text for existing images with a single click, saving you hours of manual work.

**Extensive Image Formats:** Support for all common image formats including JPG, JPEG, PNG, GIF, WebP, AVIF, and SVG files, ensuring compatibility with your entire image library.

**Bulk Processing:** Use our bulk generation tool to add alt text to multiple existing images in your media library at once.

**Processed Images History:** View and manage all images that have been processed by Alt Magic, with the ability to edit or regenerate alt text directly from this interface.

**Cover All Media Properties:** Automatically add not just alt texts, but also captions, titles, and descriptions to your images.

**Contextual And Keyword Rich:** All generated alt texts are intelligently crafted based on the content and context of your images and surrounding text.

**SEO Plugin Integration:** Seamlessly integrates with popular SEO plugins like Yoast, Rank Math, SEO Press, Squirrly SEO, and AISEO to create keyword-rich alt texts that boost your SEO.

**WooCommerce Optimization:** Enhanced support for WooCommerce stores with intelligent alt text generation that includes product names, features, and benefits in the alt text, improving your product image SEO and discoverability.

**Multilingual Support:** Generate alt texts in over 150 languages. Perfect for multilingual websites and global e-commerce stores.

**AI-Powered Image Renaming:** Automatically rename uploaded images with descriptive, SEO-friendly filenames using AI analysis of image content.

**Manual Image Renaming:** Full control with manual renaming options for fine-tuning image filenames to your specific needs.


== Frequently Asked Questions ==

= How does Alt Magic Pro generate alt text? =
Alt Magic Pro utilizes advanced AI models that analyze the content of images and the post content context to create descriptive and contextual alt texts.

= Can I edit the generated alt texts? =
Yes, all generated alt texts are editable. You can modify them as needed to better fit your content.

= Is it compatible with other plugins? =
Alt Magic Pro integrates smoothly with popular SEO plugins like Yoast, Rank Math, SEO Press, Squirrly SEO, and AISEO, enhancing your website's search engine optimization efforts.

= Does Alt Magic work with Brilliant Directories? =
Yes, Alt Magic can scan and generate alt text for Brilliant Directories pages. However, you'll need to manually upload the alt text to the BD images. A Chrome extension is planned to automate this process.

= Does Alt Magic have a web interface, or is it only available in WordPress? =
Alt Magic offers both a web app interface and WordPress plugin integration, giving you flexibility in how you use the tool.

= What languages does Alt Magic support? =
Alt Magic supports over 150 languages for alt text generation, making it perfect for multilingual websites and global e-commerce stores.

= How do the monthly credits work? =
Monthly credits are generous and unused credits roll over annually. This means you can save up credits during slower months and use them when you have more images to process.

= Can I use Alt Magic for bulk processing? =
Yes, Alt Magic offers bulk alt text generation. You can process multiple images at once, and there's also CSV upload support for seamless batch processing of large image libraries.

= Is Alt Magic compatible with WooCommerce? =
Yes, Alt Magic has enhanced WooCommerce support with intelligent alt text generation that includes product names, features, and benefits, improving your product image SEO and discoverability.

= How does Alt Magic handle SEO optimization? =
Alt Magic automatically embeds relevant, high-impact SEO keywords into descriptions. It integrates with popular SEO plugins to create keyword-rich alt texts that boost your search rankings.

= What's the difference between Alt Magic and other alt text tools like AltText.ai? =
Alt Magic provides better quality generations with more accurate and succinct descriptions. It uses industry-specific language instead of generic AI language, even for niche markets. Additionally, Alt Magic offers more generous plan limits and better pricing.

= Can I generate alt text for accessibility purposes rather than just SEO? =
Absolutely! Alt Magic is excellent for enhancing digital accessibility. It generates precise image descriptions that work perfectly with screen readers and assistive technologies, making your content accessible to all users.

= Does Alt Magic support context-aware alt text generation? =
Yes, Alt Magic is designed to generate contextually relevant alt texts based on the content and context of your images and surrounding text. Future updates will include enhanced context features for even more precise descriptions.

= How easy is the setup process? =
Alt Magic offers simple one-click installation through your WordPress dashboard with zero configuration required. It's truly "set it and forget it" functionality that works seamlessly with all major WordPress themes, page builders, and plugins.

= What happens to my credits if I don't use them all in a month? =
Unused monthly credits roll over and reset annually, giving you flexibility to use them when you need them most. This is especially useful for businesses with seasonal content creation patterns.

= What image formats does Alt Magic support? =
Alt Magic supports all common image formats including JPG, JPEG, PNG, GIF, WebP, AVIF, and SVG files. The plugin can process these formats to generate accurate alt text descriptions.

= Do I own the alt text I generate with Alt Magic? =
Yes, you completely own all the alt text content you generate using Alt Magic. The generated alt texts are your intellectual property and you can use, modify, or distribute them as needed for your websites and projects.

= Can I use Alt Magic with multiple sites? =
Yes, you can use Alt Magic on as many sites as you want - there's no limit! Whether you're managing one website or multiple client sites, Alt Magic works across all your WordPress installations without any restrictions.

== Screenshots ==
1. Alt text generation button on media upload screen 
2. Alt text auto generation settings
3. Bulk alt text generation
4. Bulk alt text generation with enhanced controls
5. Processed Images history page for viewing and managing generated alt texts
6. Automatic and Bulk Image renaming with AI

== Service Information ==

Alt Magic is a service-based plugin that provides AI-powered alt text generation and image renaming services. The plugin requires an active internet connection and an Alt Magic account with API credentials to function.

**Service Provider:**
This plugin connects to Alt Magic's cloud-based AI service to process images and generate alt texts and image filenames. The plugin does not perform AI processing locally.

**Remote Servers Called:**
The plugin makes API calls to the following service endpoints:
- Service Base URL: https://alt-magic-api-eabaa2c8506a.herokuapp.com
- `/image-name-generator-wp` - For AI-powered image filename generation
- `/combined-generator-wp` - For combined alt text and image name generation
- `/user-details` - For fetching user account information and credit balance
- `/wp-plugin-events/wp-plugin-events` - For plugin usage analytics and events

**Account Requirements:**
An Alt Magic account and API key are required to use this plugin. Users must:
1. Sign up for an account at https://altmagic.pro
2. Obtain an API key from their Alt Magic dashboard
3. Enter the API key in the plugin settings

**Data Transmission:**
The plugin sends image data (image files or URLs) to the Alt Magic service for AI analysis and processing. This data is used solely to generate alt text descriptions and image filenames. The plugin also transmits basic plugin usage events for service improvement.

**Privacy and Terms:**
For detailed information about data handling, privacy practices, and terms of service, please refer to:
- [Service Link](https://altmagic.pro)
- [Terms of Use](https://altmagic.pro/terms-of-service)
- [Privacy Policy](https://altmagic.pro/privacy-policy)


== Changelog ==
= 1.6.2 =
* Added all images tab to alt text generation page
* Added filter to image renaming page
* Fixed caption and description bulk alt generation bug

= 1.6.1 =
* Auto detection of firewalls
* Small UI fixes

= 1.5.3 =
* New signup flow
* Fixed an auto image renaming bug
* Small UI fixes

= 1.5.2 =
* Fixed a bug in verbosity selection settings
* Multi selection using Shift key in the bulk alt text generation
* Small UI fixes

= 1.5.1 =
* Fixed security issues
* Improved DB calls for processing speed 

= 1.5.0 =
* Made compatible with more themes
* Fixed the key verification bug

= 1.4.9 =
* Fixed critical bug for Kadence theme and blocks

= 1.4.8 =
* Fixed critical bug that were causing "Cookies were blocked due to unexpected output" error

= 1.4.7 =
* Added custom ChatGPT prompt layer for advanced alt text generation control
* Added language selection for image renaming feature

= 0.4.6 =
* Minor UI improvements and bug fixes
* Updated WordPress compatibility to 6.8.3 latest

= 0.4.5 =
* Added AI-powered image renaming during image upload
* Added AI-powered image renaming for already uploaded images
* Added manual image renaming option
* Upgraded image vision models for better accuracy
* Added clear log option for better debugging
* Multiple small bug fixes and performance improvements

= 0.4.4 =
* Improved ui elements and modal designs

= 0.4.3 =
* Fixed bug for sites with WordPress installed inside a subfolder

= 0.4.2 =
* Fixed bug for .local sites 

= 0.4.1 =
* Fixed bug in bulk alt text generation language handling

= 0.4.0 =
* Fixed critical bug in bulk alt text generation that was causing errors in some sites

= 0.3.2 =
* Faster bulk alt text generation with user-configurable speed settings
* Enhanced bulk processing with selective image selection capabilities
* Added optimization feature for images with existing alt text

= 0.3.1 =
* Added a new "Style & Level of Detail" setting, allowing users to choose between "Elaborated," "Standard," and "Concise" alt text generation styles for more granular control over verbosity.

= 0.2.9 =
* Bug fixes and performance improvements
* Updated WordPress compatibility to 6.8.1

= 0.2.2 =
* Initial release with core features including auto-generation of alt texts, bulk generation, and Yoast SEO integration.

= 0.2.3 =
* Added language support for alt text generation.
* Added eCommerce optimized alt text generation.

= 0.2.4 =
* Added support for WordPress 6.7.1

= 0.2.5 =
* Added integration with Squirrly SEO for keyword-rich alt texts.
* Added integration with SEO Press for keyword-rich alt texts.
* Added integration with Rank Math SEO for keyword-rich alt texts.
* Added integration with AISEO plugin for keyword-rich alt texts.

= 0.2.6 =
* Bug fixes

= 0.2.7 =
* Added Processed Images feature to view history of all images processed by Alt Magic
* Improved alt text column in media library for better visibility and management
* Fixed issue with quotes in alt text display
* Enhanced user interface with visual feedback when generating alt text

= 0.2.8 =
* Added site-wide language selection feature, allowing each site to have its own preferred language for alt text generation
* Bug fixes

== Upgrade Notice ==
= 1.6.2 =
* Added all images tab to alt text generation page, filter on image renaming page, and fixed caption/description not being updated during bulk alt text generation when flags are enabled.

= 1.6.1 =
* Alt Magic can now auto detect if the site is using firewall and generate alt text for it

= 1.5.3 =
* Fixed an image renaming bug and added new signup flow. Update recommended for all users.

= 1.5.2 =
* This version fixes verbosity selection settings bug, adds shift-click multi-selection for bulk operations, and includes various UI improvements. Update recommended for all users.

= 1.5.1 =
* This version includes important security improvements and image processing speed 

= 1.5.0 =
* Improved compatibility with more WordPress themes and fixed API key verification issues. Update recommended for all users.

= 1.4.9 =
* CRITICAL UPDATE: Fixes critical bug for Kadence theme and blocks

= 1.4.8 =
* CRITICAL UPDATE: Fixes "Cookies were blocked" error that prevented some users from logging into WordPress admin.

= 1.4.7 =
* This version introduces custom ChatGPT prompt layer for advanced alt text generation control and language selection for image renaming. Update recommended for all users.

= 0.4.6 =
* Minor UI improvements and bug fixes. Update recommended for all users.

= 0.4.5 =
* This version introduces AI-powered image renaming features for both new uploads and existing images, upgraded vision models, and improved debugging capabilities. Update recommended for all users.

= 0.4.4 =
* This version introduces improved user experience elements. Update recommended for all users.

= 0.4.3 =
* Fixed bug for sites with WordPress installed inside a subfolder. Update recommended for all users.

= 0.4.2 =
* Fixed bug for .local sites. Update recommended for all users.

= 0.4.1 =
* This version fixes a bug in bulk alt text generation language handling. Update recommended for all users.

= 0.4.0 =
* This version fixes a critical bug in bulk alt text generation. If you were experiencing issues with bulk processing, this update is highly recommended.

= 0.3.2 =
* This version introduces faster bulk processing with configurable speed settings and selective image selection. Users can now choose which images to process and optimize existing alt text for better results.

= 0.3.1 =
* This version introduces a new "Style & Level of Detail" setting. After upgrading, you can configure this in the AI Settings page to control the verbosity of your generated alt texts.

= 0.2.9 =
* Bug fixes and performance improvements
* Updated WordPress compatibility to 6.8.1

= 0.2.8 =
* Added site-wide language selection feature, allowing each site to have its own preferred language for alt text generation
* Bug fixes

= 0.2.7 =
* Added Processed Images feature to view and edit historical processed images
* Improved media library with alt text column and visual enhancements
* Fixed various display issues with special characters in alt text


