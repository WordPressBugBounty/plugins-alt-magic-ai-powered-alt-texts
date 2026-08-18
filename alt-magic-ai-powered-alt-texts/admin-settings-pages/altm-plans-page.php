<?php

// Ensure this file is not accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the internal Plans & Pricing admin page URL.
 *
 * @param string $view Pricing view to open: credits or lifetime.
 * @return string
 */
function altm_get_plans_page_url($view = 'credits') {
    $view = $view === 'lifetime' ? 'lifetime' : 'credits';

    return add_query_arg(
        array(
            'page' => 'alt-magic-plans',
            'view' => $view,
        ),
        admin_url('admin.php')
    );
}

/**
 * Add the connected account email to a Lemon Squeezy checkout URL.
 *
 * The email is only sent after the administrator clicks a purchase button.
 *
 * @param string $checkout_url Base checkout URL.
 * @return string
 */
function altm_get_checkout_url($checkout_url) {
    $user_email = sanitize_email(get_option('alt_magic_user_id', ''));

    if (!is_email($user_email)) {
        return $checkout_url;
    }

    return add_query_arg('checkout[email]', $user_email, $checkout_url);
}

/**
 * Get the pricing data mirrored from the official Alt Magic pricing pages.
 *
 * @return array
 */
function altm_get_pricing_plans() {
    $credit_features = array(
        array('label' => '1 credit = 1 image', 'icon' => 'performance'),
        array('label' => 'Credits never expire', 'icon' => 'clock'),
        array('label' => 'Unlimited websites', 'icon' => 'networking'),
        array('label' => 'SEO keyword optimization', 'icon' => 'search'),
        array('label' => 'Scan pages for empty alt', 'icon' => 'admin-links'),
        array('label' => 'WordPress & WooCommerce', 'icon' => 'wordpress'),
        array('label' => 'Chrome & Firefox extensions', 'icon' => 'superhero-alt'),
        array('label' => 'Shopify app', 'icon' => 'cart'),
        array('label' => '150+ languages', 'icon' => 'translation'),
        array('label' => 'API Access', 'icon' => 'editor-code'),
        array('label' => 'AI Image Renaming', 'icon' => 'format-image', 'badge' => 'Beta'),
    );

    return array(
        'credits' => array(
            array(
                'name' => 'Starter',
                'price' => '$9',
                'original_price' => '$19',
                'credits' => '500 credits',
                'detail' => '2¢ per image',
                'badge' => '',
                'featured' => false,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/0f44be3a-ff19-44ec-adb2-bf0f9828c01c',
                'features' => $credit_features,
            ),
            array(
                'name' => 'Growth',
                'price' => '$49',
                'original_price' => '$99',
                'credits' => '5,000 credits',
                'detail' => '1¢ per image',
                'saving' => 'Save 50%',
                'badge' => '',
                'featured' => false,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/c47a3a86-28bf-4a5d-8b4d-a42248311a08',
                'features' => $credit_features,
            ),
            array(
                'name' => 'Pro',
                'price' => '$99',
                'original_price' => '$199',
                'credits' => '15,000 credits',
                'detail' => '0.7¢ per image',
                'saving' => 'Save 50%',
                'badge' => 'Most popular',
                'featured' => true,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/440149a7-9a1b-4b13-9707-a25b89c0f568',
                'features' => $credit_features,
            ),
            array(
                'name' => 'Scale',
                'price' => '$349',
                'original_price' => '$699',
                'credits' => '60,000 credits',
                'detail' => '0.6¢ per image',
                'saving' => 'Save 50%',
                'badge' => 'Best value',
                'featured' => false,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/fb6589ca-ac82-4f71-ab95-05be3a6e279d',
                'features' => $credit_features,
            ),
        ),
        'lifetime' => array(
            array(
                'name' => 'Starter',
                'price' => '$49',
                'original_price' => '$98',
                'credits' => '500 credits/month',
                'monthly_allocation' => '500 credits',
                'detail' => 'per month forever',
                'badge' => '',
                'featured' => false,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/763e0c13-31f8-437b-a393-2c8254b0cbc1',
                'features' => array(
                    array('label' => '500 credits per month', 'icon' => 'performance'),
                    array('label' => '1 credit = 1 image alt text', 'icon' => 'format-image'),
                    array('label' => 'Rolling credits (1-year window)', 'icon' => 'clock'),
                    array('label' => 'Smart image renaming', 'icon' => 'format-image'),
                    array('label' => 'Multi-site support', 'icon' => 'networking'),
                    array('label' => 'API access', 'icon' => 'editor-code'),
                    array('label' => 'Chrome & Firefox extensions', 'icon' => 'superhero-alt'),
                    array('label' => 'WordPress & WooCommerce plugins', 'icon' => 'wordpress'),
                    array('label' => 'Shopify integration', 'icon' => 'cart'),
                    array('label' => 'Make.com integration', 'icon' => 'randomize'),
                    array('label' => 'Zapier integration (coming soon)', 'icon' => 'controls-repeat'),
                    array('label' => 'Alt text in 150+ languages', 'icon' => 'translation'),
                    array('label' => 'Priority chat support', 'icon' => 'admin-comments'),
                ),
            ),
            array(
                'name' => 'Professional',
                'price' => '$149',
                'original_price' => '$298',
                'credits' => '1,500 credits/month',
                'monthly_allocation' => '1,500 credits',
                'detail' => 'per month forever',
                'badge' => 'Selling fast',
                'featured' => true,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/01362d20-e10f-47e9-854a-8dca227f02ab',
                'features' => array(
                    array('label' => '1,500 credits per month', 'icon' => 'performance'),
                    array('label' => '1 credit = 1 image alt text', 'icon' => 'format-image'),
                    array('label' => 'Rolling credits (1-year window)', 'icon' => 'clock'),
                    array('label' => 'Smart image renaming', 'icon' => 'format-image'),
                    array('label' => 'Multi-site support', 'icon' => 'networking'),
                    array('label' => 'API access', 'icon' => 'editor-code'),
                    array('label' => 'Chrome & Firefox extensions', 'icon' => 'superhero-alt'),
                    array('label' => 'WordPress & WooCommerce plugins', 'icon' => 'wordpress'),
                    array('label' => 'Shopify integration', 'icon' => 'cart'),
                    array('label' => 'Make.com integration', 'icon' => 'randomize'),
                    array('label' => 'Zapier integration (coming soon)', 'icon' => 'controls-repeat'),
                    array('label' => 'Alt text in 150+ languages', 'icon' => 'translation'),
                    array('label' => 'Priority chat support', 'icon' => 'admin-comments'),
                ),
            ),
            array(
                'name' => 'Business',
                'price' => '$349',
                'original_price' => '$698',
                'credits' => '5,000 credits/month',
                'monthly_allocation' => '5,000 credits',
                'detail' => 'per month forever',
                'badge' => '',
                'featured' => false,
                'checkout_url' => 'https://alt-magic.lemonsqueezy.com/checkout/buy/e5959c86-7194-432d-9463-ff9dfa12e135',
                'features' => array(
                    array('label' => '5,000 credits per month', 'icon' => 'performance'),
                    array('label' => '1 credit = 1 image alt text', 'icon' => 'format-image'),
                    array('label' => 'Rolling credits (1-year window)', 'icon' => 'clock'),
                    array('label' => 'Smart image renaming', 'icon' => 'format-image'),
                    array('label' => 'Multi-site support', 'icon' => 'networking'),
                    array('label' => 'API access', 'icon' => 'editor-code'),
                    array('label' => 'Chrome & Firefox extensions', 'icon' => 'superhero-alt'),
                    array('label' => 'WordPress & WooCommerce plugins', 'icon' => 'wordpress'),
                    array('label' => 'Shopify integration', 'icon' => 'cart'),
                    array('label' => 'Make.com integration', 'icon' => 'randomize'),
                    array('label' => 'Zapier integration (coming soon)', 'icon' => 'controls-repeat'),
                    array('label' => 'Alt text in 150+ languages', 'icon' => 'translation'),
                    array('label' => 'Priority chat + call support', 'icon' => 'admin-comments'),
                ),
            ),
        ),
    );
}

/**
 * Read and normalize the selected plans view.
 *
 * @return string
 */
function altm_get_selected_plans_view() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display preference.
    $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'credits';

    return $view === 'lifetime' ? 'lifetime' : 'credits';
}

/**
 * Render a collection of pricing cards.
 *
 * @param array  $plans   Plans to render.
 * @param string $type    credits or lifetime.
 * @param bool   $compact Whether this is the exhausted-credit modal.
 * @return void
 */
function altm_render_plan_cards($plans, $type, $compact = false) {
    $grid_class = $compact ? ' altm-plans-grid--compact' : '';
    ?>
    <div class="altm-plans-grid<?php echo esc_attr($grid_class); ?>">
        <?php foreach ($plans as $plan) : ?>
            <?php
            $card_classes = 'altm-plan-card';
            $plan_key = sanitize_html_class(strtolower($plan['name']));
            if (!empty($plan['featured'])) {
                $card_classes .= ' altm-plan-card--featured';
            }
            ?>
            <article class="<?php echo esc_attr($card_classes); ?>">
                <div class="altm-plan-card__visual altm-plan-card__visual--<?php echo esc_attr($type); ?> altm-plan-card__visual--<?php echo esc_attr($plan_key); ?>">
                    <div class="altm-plan-card__title-group">
                        <div class="altm-plan-card__name-row">
                            <h3><?php echo esc_html($plan['name']); ?></h3>
                            <span class="altm-plan-card__sale">
                                <span class="dashicons dashicons-performance" aria-hidden="true"></span>
                                <?php echo esc_html__('50% off', 'alt-magic'); ?>
                            </span>
                        </div>
                        <?php if (!empty($plan['badge'])) : ?>
                            <span class="altm-plan-card__badge"><?php echo esc_html($plan['badge']); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="altm-plan-card__price">
                        <strong><?php echo esc_html($plan['price']); ?></strong>
                        <span>one-time</span>
                        <del><?php echo esc_html($plan['original_price']); ?></del>
                    </p>
                </div>

                <div class="altm-plan-card__body">
                    <p class="altm-plan-card__terms">
                        <?php echo $type === 'lifetime' ? esc_html__('one-time · lifetime access', 'alt-magic') : esc_html__('one-time · no expiry', 'alt-magic'); ?>
                    </p>
                    <?php if ($type === 'lifetime') : ?>
                        <p class="altm-plan-card__credits altm-plan-card__credits--lifetime">
                            <strong><?php echo esc_html($plan['monthly_allocation']); ?></strong><span>/month</span>
                        </p>
                    <?php else : ?>
                        <p class="altm-plan-card__credits"><?php echo esc_html($plan['credits']); ?></p>
                    <?php endif; ?>
                    <?php if ($type === 'lifetime') : ?>
                        <div class="altm-plan-card__divider" aria-hidden="true"></div>
                        <div class="altm-plan-card__lifetime-allocation">
                            <strong><?php echo esc_html($plan['monthly_allocation']); ?></strong>
                            <span><?php echo esc_html($plan['detail']); ?></span>
                        </div>
                    <?php else : ?>
                        <div class="altm-plan-card__detail-row">
                            <p class="altm-plan-card__detail"><?php echo esc_html($plan['detail']); ?></p>
                            <?php if (!empty($plan['saving'])) : ?>
                                <span class="altm-plan-card__saving"><?php echo esc_html($plan['saving']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="altm-plan-card__divider" aria-hidden="true"></div>
                    <?php endif; ?>

                    <a
                        class="altm-plan-card__button"
                        href="<?php echo esc_url(altm_get_checkout_url($plan['checkout_url'])); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <?php echo $type === 'lifetime' ? esc_html__('Get lifetime access', 'alt-magic') : esc_html(sprintf(__('Choose %s', 'alt-magic'), $plan['name'])); ?>
                    </a>

                    <ul class="altm-plan-card__features">
                        <?php
                        $features = $compact ? array_slice($plan['features'], 0, 3) : $plan['features'];
                        foreach ($features as $feature) :
                            $feature_label = is_array($feature) && isset($feature['label']) ? $feature['label'] : $feature;
                            $feature_icon = is_array($feature) && isset($feature['icon']) ? sanitize_html_class($feature['icon']) : 'yes-alt';
                            $feature_badge = is_array($feature) && isset($feature['badge']) ? $feature['badge'] : '';
                            ?>
                            <li>
                                <span class="altm-plan-card__feature-icon dashicons dashicons-<?php echo esc_attr($feature_icon); ?>" aria-hidden="true"></span>
                                <span class="altm-plan-card__feature-label">
                                    <?php echo esc_html($feature_label); ?>
                                    <?php if ($feature_badge !== '') : ?>
                                        <em><?php echo esc_html($feature_badge); ?></em>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($type === 'lifetime') : ?>
                        <p class="altm-plan-card__guarantee">60-day money-back guarantee</p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Render customer and review-platform trust signals from the Alt Magic pricing page.
 *
 * @param bool $compact Whether this is the exhausted-credit modal.
 * @return void
 */
function altm_render_plans_trust_strip($compact = false) {
    $asset_base_url = plugin_dir_url(__FILE__) . '../assets/';
    $avatars = array(
        'altm-rating-avatar-1.jpg',
        'altm-rating-avatar-2.png',
        'altm-rating-avatar-3.jpg',
        'altm-rating-avatar-4.jpeg',
    );
    $platforms = array(
        array(
            'key' => 'wordpress',
            'name' => 'WordPress',
            'logo' => 'altm-wordpress-rating-logo.svg',
            'label' => __('rated 5 in WordPress', 'alt-magic'),
        ),
        array(
            'key' => 'g2',
            'name' => 'G2',
            'logo' => 'altm-g2-rating-logo.svg',
            'label' => __('rated 4.9 in G2', 'alt-magic'),
        ),
        array(
            'key' => 'trustpilot',
            'name' => 'Trustpilot',
            'logo' => 'altm-trustpilot-rating-logo.svg',
            'label' => __('rated 5 in Trustpilot', 'alt-magic'),
        ),
    );
    ?>
    <div
        class="altm-plans-trust<?php echo $compact ? ' altm-plans-trust--compact' : ''; ?>"
        aria-label="<?php echo esc_attr__('Customer ratings', 'alt-magic'); ?>"
    >
        <div class="altm-plans-trust__customers">
            <div class="altm-plans-trust__avatars" aria-hidden="true">
                <?php foreach ($avatars as $avatar) : ?>
                    <img src="<?php echo esc_url($asset_base_url . $avatar); ?>" alt="" width="36" height="36">
                <?php endforeach; ?>
            </div>
            <div class="altm-plans-trust__rating">
                <div class="altm-plans-trust__stars" aria-label="<?php echo esc_attr__('5 out of 5 stars', 'alt-magic'); ?>">
                    <?php for ($star = 0; $star < 5; $star++) : ?>
                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                    <?php endfor; ?>
                </div>
                <p><?php echo esc_html($compact ? __('Loved by 10,000+ marketers and agencies', 'alt-magic') : __('Loved by marketers and agencies', 'alt-magic')); ?></p>
            </div>
        </div>

        <div class="altm-plans-trust__platforms">
            <?php foreach ($platforms as $platform) : ?>
                <div class="altm-plans-trust__platform altm-plans-trust__platform--<?php echo esc_attr($platform['key']); ?>">
                    <span class="altm-plans-trust__platform-logo">
                        <img
                            src="<?php echo esc_url($asset_base_url . $platform['logo']); ?>"
                            alt="<?php echo esc_attr($platform['name']); ?>"
                        >
                    </span>
                    <span><?php echo esc_html($platform['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

/**
 * Render the striped section break used by the public Alt Magic pricing page.
 *
 * @return void
 */
function altm_render_plans_section_divider() {
    ?>
    <div class="altm-plans-section-divider" aria-hidden="true"></div>
    <?php
}

/**
 * Render the high-volume offer below the credit-pack cards.
 *
 * @return void
 */
function altm_render_plans_enterprise_offer() {
    ?>
    <section class="altm-plans-enterprise" aria-labelledby="altm-plans-enterprise-title">
        <div class="altm-plans-enterprise__heading">
            <h2 id="altm-plans-enterprise-title"><?php echo esc_html__('Need more than 60,000 credits?', 'alt-magic'); ?></h2>
            <p><?php echo esc_html__("Let's design a calm, custom Enterprise plan.", 'alt-magic'); ?></p>
        </div>
        <div class="altm-plans-enterprise__card">
            <div>
                <span><?php echo esc_html__('Enterprise & high-volume', 'alt-magic'); ?></span>
                <h3><?php echo esc_html__('Enterprise plans for serious image volume discounts.', 'alt-magic'); ?></h3>
                <p><?php echo esc_html__('Ideal for agencies, platforms, and teams managing large image libraries.', 'alt-magic'); ?></p>
            </div>
            <a href="https://tally.so/r/nWVqBL" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html__('Talk to us', 'alt-magic'); ?>
            </a>
        </div>
    </section>
    <?php
}

/**
 * Render the continuously moving customer-review marquee.
 *
 * @return void
 */
function altm_render_plans_reviews() {
    $asset_base_url = plugin_dir_url(__FILE__) . '../assets/';
    $reviews = array(
        array(
            'quote' => 'I wish more people know about this tool. If you want to automatically process on your Alt Text Images with on-point details, Alt Magic is the answer.',
            'name' => 'gogrowasia',
            'role' => '@gogrowasia',
            'avatar' => 'altm-review-gogrowasia.png',
        ),
        array(
            'quote' => 'AltMagic takes the mind-numbing task of writing alt text and makes it effortless. Its AI descriptions are impressively accurate, vivid, and way better than the half-baked guesses I used to slap on my images.',
            'name' => 'Ramyt R.',
            'role' => 'UI / UX Designer + Web Developer',
            'avatar' => 'altm-review-ramyt.png',
        ),
        array(
            'quote' => 'What I love most is how much time it saves me. The descriptions actually make sense, feel natural, and help with accessibility and SEO at the same time.',
            'name' => 'Matias W.',
            'role' => 'Coordinador de servicios',
            'avatar' => 'altm-review-matias.jpg',
        ),
        array(
            'quote' => 'Alt Magic saved me so much time by automatically generating alt text for all my images. The AI descriptions are accurate and make my site more accessible.',
            'name' => 'mairaforesto',
            'role' => '@mairaforesto',
            'avatar' => 'altm-review-mairaforesto.jpg',
        ),
        array(
            'quote' => 'The most helpful about using this simple plugin is that you can simply do a bulk update of any missing alt attributes. It actually will give you a description something more than maybe what your file name is.',
            'name' => 'Jason C.',
            'role' => 'Founder',
            'avatar' => 'altm-review-jason.png',
        ),
        array(
            'quote' => 'Plugin is easy to handle, can bulk generate images alt descriptions and also generate them as you upload new images.',
            'name' => 'net_runner',
            'role' => '@net_runner',
            'avatar' => 'altm-review-net-runner.png',
        ),
        array(
            'quote' => "I've been using Alt Magic for about a month now, and I'm genuinely impressed. The plugin works seamlessly in the background and has helped increase my website traffic.",
            'name' => 'Mourad Al Damarawy',
            'role' => 'Website Owner',
            'avatar' => 'altm-review-mourad.png',
        ),
    );
    ?>
    <section class="altm-plans-reviews" aria-labelledby="altm-plans-reviews-title">
        <header class="altm-plans-reviews__heading">
            <h2 id="altm-plans-reviews-title"><?php echo esc_html__('What our users say', 'alt-magic'); ?></h2>
            <p><?php echo esc_html__('Real reviews from marketers and developers', 'alt-magic'); ?></p>
        </header>
        <div class="altm-plans-reviews__viewport">
            <div class="altm-plans-reviews__track">
                <?php for ($review_group = 0; $review_group < 2; $review_group++) : ?>
                    <div class="altm-plans-reviews__group"<?php echo $review_group === 1 ? ' aria-hidden="true"' : ''; ?>>
                        <?php foreach ($reviews as $review) : ?>
                            <article class="altm-plans-review-card">
                                <div class="altm-plans-review-card__stars" aria-label="<?php echo esc_attr__('5 out of 5 stars', 'alt-magic'); ?>">
                                    <?php for ($star = 0; $star < 5; $star++) : ?>
                                        <span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
                                    <?php endfor; ?>
                                </div>
                                <p class="altm-plans-review-card__quote">“<?php echo esc_html($review['quote']); ?>”</p>
                                <footer class="altm-plans-review-card__author">
                                    <img src="<?php echo esc_url($asset_base_url . $review['avatar']); ?>" alt="" width="40" height="40">
                                    <div>
                                        <strong><?php echo esc_html($review['name']); ?></strong>
                                        <span><?php echo esc_html($review['role']); ?></span>
                                    </div>
                                </footer>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Render payment-plan questions beneath the standalone pricing page.
 *
 * @return void
 */
function altm_render_plans_faqs() {
    $faqs = array(
        array(
            'question' => __('How can I contact Alt Magic support?', 'alt-magic'),
            'answer_html' => sprintf(
                /* translators: 1: live chat URL, 2: support email URL, 3: support call URL. */
                __('We’re here to help. Reach us through <a href="%1$s" target="_blank" rel="noopener noreferrer">live chat on our website</a>, email us at <a href="%2$s">advait@altmagic.pro</a>, or <a href="%3$s" target="_blank" rel="noopener noreferrer">book a free support call</a>.', 'alt-magic'),
                esc_url('https://www.altmagic.pro/'),
                esc_url('mailto:advait@altmagic.pro'),
                esc_url('https://www.altmagic.pro/book-a-call')
            ),
        ),
        array(
            'question' => __('How do credits work?', 'alt-magic'),
            'answer' => __('Each credit generates alt text for one image. 1 credit = 1 image, every time, regardless of image format, size, or language.', 'alt-magic'),
        ),
        array(
            'question' => __('Do credit pack credits expire?', 'alt-magic'),
            'answer' => __('No. Credits from one-time credit packs never expire, so you can use them whenever you want, at your own pace.', 'alt-magic'),
        ),
        array(
            'question' => __('How does the lifetime deal work?', 'alt-magic'),
            'answer' => __('You make one payment and receive your plan’s credits every month for life. It is not a recurring subscription.', 'alt-magic'),
        ),
        array(
            'question' => __('Do unused lifetime credits roll over?', 'alt-magic'),
            'answer' => __('Yes. Unused credits from a lifetime plan can roll over for up to one year.', 'alt-magic'),
        ),
        array(
            'question' => __('Can I use Alt Magic on multiple websites?', 'alt-magic'),
            'answer' => __('Yes. There is no limit to how many websites or integrations you can associate with your account.', 'alt-magic'),
        ),
        array(
            'question' => __('How is payment handled?', 'alt-magic'),
            'answer' => __('Checkout is securely handled by Lemon Squeezy in a new tab. Your credits are added to your connected Alt Magic account.', 'alt-magic'),
        ),
        array(
            'question' => __('Is there a money-back guarantee?', 'alt-magic'),
            'answer' => __('Yes. Lifetime deal purchases include a 60-day money-back guarantee.', 'alt-magic'),
        ),
        array(
            'question' => __('What’s included in all plans?', 'alt-magic'),
            'answer' => __('All plans include the WordPress and WooCommerce plugin, browser extension access, API access, 150+ language support, and use on unlimited websites.', 'alt-magic'),
        ),
        array(
            'question' => __('Can Alt Magic help with ADA and WCAG accessibility?', 'alt-magic'),
            'answer' => __('Yes. Alt Magic can support WCAG 2.2 Success Criterion 1.1.1 and broader ADA accessibility work by creating text alternatives for informative images. Generated alt text does not guarantee compliance on its own, so review each result in context, leave decorative images with empty alt text, and assess the rest of your website as well.', 'alt-magic'),
        ),
        array(
            'question' => __('Can I edit the generated alt text?', 'alt-magic'),
            'answer' => __('Yes. Every generated alt text is editable in WordPress, so you can refine the wording to match the image’s purpose and the surrounding page content.', 'alt-magic'),
        ),
        array(
            'question' => __('Does Alt Magic generate context-aware alt text?', 'alt-magic'),
            'answer' => __('Yes. Alt Magic analyzes the image together with available post, page, or product context to create more relevant descriptions.', 'alt-magic'),
        ),
        array(
            'question' => __('Does Alt Magic work with WooCommerce and SEO plugins?', 'alt-magic'),
            'answer' => __('Yes. Alt Magic supports WooCommerce product-image workflows and works with Yoast SEO, Rank Math, SEOPress, Squirrly SEO, and AIOSEO for keyword-aware alt text.', 'alt-magic'),
        ),
        array(
            'question' => __('Can I generate alt text in bulk?', 'alt-magic'),
            'answer' => __('Yes. You can generate alt text for existing WordPress media-library images in bulk and automatically process newly uploaded images.', 'alt-magic'),
        ),
        array(
            'question' => __('Which languages and image formats are supported?', 'alt-magic'),
            'answer' => __('Alt Magic generates alt text in more than 150 languages and supports common formats including JPG, JPEG, PNG, GIF, WebP, AVIF, HEIC, and SVG.', 'alt-magic'),
        ),
        array(
            'question' => __('Do I own the alt text generated with Alt Magic?', 'alt-magic'),
            'answer' => __('Yes. You own the alt text generated for your images and can edit, reuse, or distribute it across your websites and projects.', 'alt-magic'),
        ),
        array(
            'question' => __('Does Alt Magic include free monthly credits?', 'alt-magic'),
            'answer' => __('Yes. Your account includes 50 free credits every month for alt text generation and AI image renaming. The free credits refresh on the first day of each month.', 'alt-magic'),
        ),
    );
    ?>
    <section class="altm-plans-faq" data-altm-plans-faq aria-labelledby="altm-plans-faq-title">
        <div class="altm-plans-faq__heading">
            <h2 id="altm-plans-faq-title"><?php echo esc_html__('Frequently asked questions', 'alt-magic'); ?></h2>
        </div>
        <div class="altm-plans-faq__items">
            <?php foreach ($faqs as $faq) : ?>
                <details class="altm-plans-faq__item">
                    <summary>
                        <span><?php echo esc_html($faq['question']); ?></span>
                        <span class="altm-plans-faq__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </summary>
                    <div class="altm-plans-faq__answer">
                        <p>
                            <?php
                            if (!empty($faq['answer_html'])) {
                                echo wp_kses(
                                    $faq['answer_html'],
                                    array(
                                        'a' => array(
                                            'href' => true,
                                            'target' => true,
                                            'rel' => true,
                                        ),
                                    )
                                );
                            } else {
                                echo esc_html($faq['answer']);
                            }
                            ?>
                        </p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

/**
 * Render the shared pricing tabs and cards.
 *
 * @param string $context page or modal.
 * @param string $selected_view credits or lifetime.
 * @return void
 */
function altm_render_plans_surface($context = 'page', $selected_view = 'credits') {
    $plans = altm_get_pricing_plans();
    $is_modal = $context === 'modal';
    $compact = false;
    $surface_id = $is_modal ? 'altm-plans-modal-surface' : 'altm-plans-page-surface';
    $selected_view = $selected_view === 'lifetime' ? 'lifetime' : 'credits';
    ?>
    <section
        id="<?php echo esc_attr($surface_id); ?>"
        class="altm-plans-surface<?php echo $compact ? ' altm-plans-surface--compact' : ''; ?>"
        data-altm-plans-surface
        data-selected-view="<?php echo esc_attr($selected_view); ?>"
    >
        <?php if ($compact) : ?>
            <?php altm_render_plans_trust_strip(true); ?>
        <?php endif; ?>

        <div class="altm-plans-switcher">
            <div class="altm-plans-tabs" role="tablist" aria-label="<?php echo esc_attr__('Choose a pricing option', 'alt-magic'); ?>">
                <button
                    type="button"
                    class="altm-plans-tab<?php echo $selected_view === 'credits' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $selected_view === 'credits' ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr($surface_id); ?>-credits"
                    data-altm-plan-tab="credits"
                >
                    <span class="altm-plans-tab__title"><?php echo esc_html__('Credit Packs', 'alt-magic'); ?></span>
                    <small><?php echo esc_html__('Buy once · no expiry', 'alt-magic'); ?></small>
                    <span class="altm-plans-tab__selection" aria-hidden="true"><?php echo esc_html__('Selected', 'alt-magic'); ?></span>
                </button>
                <button
                    type="button"
                    class="altm-plans-tab altm-plans-tab--promo<?php echo $selected_view === 'lifetime' ? ' is-active' : ''; ?>"
                    role="tab"
                    aria-selected="<?php echo $selected_view === 'lifetime' ? 'true' : 'false'; ?>"
                    aria-controls="<?php echo esc_attr($surface_id); ?>-lifetime"
                    data-altm-plan-tab="lifetime"
                >
                    <span class="altm-plans-tab__title">
                        <span class="altm-plans-tab__icon dashicons dashicons-clock" aria-hidden="true"></span>
                        <?php echo esc_html__('Lifetime Deal', 'alt-magic'); ?>
                        <em><?php echo esc_html__('Leaving Soon', 'alt-magic'); ?></em>
                    </span>
                    <small><?php echo esc_html__('Monthly credits forever', 'alt-magic'); ?></small>
                    <span class="altm-plans-tab__selection" aria-hidden="true"><?php echo esc_html__('Selected', 'alt-magic'); ?></span>
                </button>
            </div>
        </div>

        <div
            id="<?php echo esc_attr($surface_id); ?>-credits"
            class="altm-plans-panel<?php echo $selected_view === 'credits' ? ' is-active' : ''; ?>"
            role="tabpanel"
            data-altm-plan-panel="credits"
            <?php echo $selected_view === 'credits' ? '' : 'hidden'; ?>
        >
            <?php if ($compact) : ?>
                <div class="altm-plans-panel__heading">
                    <div>
                        <span class="altm-plans-eyebrow"><?php echo esc_html__('Pay as you go', 'alt-magic'); ?></span>
                        <h2><?php echo esc_html__('Credits that never expire', 'alt-magic'); ?></h2>
                    </div>
                    <p><?php echo esc_html__('Use every Alt Magic feature on unlimited websites. No subscription.', 'alt-magic'); ?></p>
                </div>
            <?php else : ?>
                <div class="altm-plans-panel__heading altm-plans-panel__heading--pricing">
                    <div class="altm-plans-panel__title-row">
                        <h2><?php echo esc_html__('Choose your plan', 'alt-magic'); ?></h2>
                        <span class="altm-plans-panel__promotion"><span class="dashicons dashicons-performance" aria-hidden="true"></span><?php echo esc_html__('50% off limited time', 'alt-magic'); ?></span>
                    </div>
                    <p><?php echo esc_html__('One-time purchase. All features included. No subscriptions.', 'alt-magic'); ?></p>
                    <div class="altm-plans-availability" aria-label="<?php echo esc_attr__('70% of promotional seats claimed', 'alt-magic'); ?>">
                        <div><span><?php echo esc_html__('70% of seats claimed', 'alt-magic'); ?></span><span><?php echo esc_html__('Hurry Up!', 'alt-magic'); ?></span></div>
                        <span class="altm-plans-availability__track"><span></span></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php altm_render_plan_cards($plans['credits'], 'credits', $compact); ?>
            <?php if (!$compact) : ?>
                <?php altm_render_plans_enterprise_offer(); ?>
            <?php endif; ?>
        </div>

        <div
            id="<?php echo esc_attr($surface_id); ?>-lifetime"
            class="altm-plans-panel<?php echo $selected_view === 'lifetime' ? ' is-active' : ''; ?>"
            role="tabpanel"
            data-altm-plan-panel="lifetime"
            <?php echo $selected_view === 'lifetime' ? '' : 'hidden'; ?>
        >
            <?php if ($compact) : ?>
                <div class="altm-plans-panel__heading altm-plans-panel__heading--lifetime">
                    <div>
                        <span class="altm-plans-eyebrow"><?php echo esc_html__('50% off · limited-time promotion', 'alt-magic'); ?></span>
                        <h2><?php echo esc_html__('One payment. Monthly credits for life.', 'alt-magic'); ?></h2>
                    </div>
                    <p><?php echo esc_html__('Your credits refresh every month, with unused credits rolling for up to one year.', 'alt-magic'); ?></p>
                </div>
            <?php else : ?>
                <div class="altm-plans-panel__heading altm-plans-panel__heading--pricing">
                    <div class="altm-plans-panel__title-row">
                        <h2><?php echo esc_html__('Choose your lifetime plan', 'alt-magic'); ?></h2>
                        <span class="altm-plans-panel__promotion"><span class="dashicons dashicons-performance" aria-hidden="true"></span><?php echo esc_html__('50% off limited time', 'alt-magic'); ?></span>
                    </div>
                    <p><?php echo esc_html__('One payment. Monthly credits forever. No recurring subscription.', 'alt-magic'); ?></p>
                    <div class="altm-plans-availability" aria-label="<?php echo esc_attr__('70% of promotional seats claimed', 'alt-magic'); ?>">
                        <div><span><?php echo esc_html__('70% of seats claimed', 'alt-magic'); ?></span><span><?php echo esc_html__('Hurry Up!', 'alt-magic'); ?></span></div>
                        <span class="altm-plans-availability__track"><span></span></span>
                    </div>
                </div>
            <?php endif; ?>
            <?php altm_render_plan_cards($plans['lifetime'], 'lifetime', $compact); ?>
        </div>
    </section>
    <?php
}

/**
 * Render the pricing-page composition shared by the page and modal.
 *
 * @param string $selected_view credits or lifetime.
 * @param string $context       page or modal.
 * @return void
 */
function altm_render_plans_page_content($selected_view = 'credits', $context = 'page') {
    ?>
    <header class="altm-plans-hero">
        <div class="altm-plans-hero__copy">
            <span class="altm-plans-eyebrow"><?php echo esc_html__('Pricing', 'alt-magic'); ?></span>
            <h1><?php echo esc_html__('Pay once, use forever.', 'alt-magic'); ?></h1>
            <p>
                <?php echo esc_html__('No subscriptions. No expiry. Buy credits and generate alt text for', 'alt-magic'); ?>
                <em><?php echo esc_html__('unlimited websites', 'alt-magic'); ?></em>.<br>
                <?php echo esc_html__('Start with 50 free credits every month. No credit card required.', 'alt-magic'); ?>
            </p>
            <?php altm_render_plans_trust_strip(false); ?>
        </div>
    </header>

    <?php altm_render_plans_section_divider(); ?>
    <?php altm_render_plans_surface($context, $selected_view); ?>
    <?php altm_render_plans_section_divider(); ?>
    <?php altm_render_plans_reviews(); ?>
    <?php altm_render_plans_section_divider(); ?>
    <?php altm_render_plans_faqs(); ?>
    <?php
}

/**
 * Render the standalone Plans & Pricing page.
 *
 * @return void
 */
function altm_render_plans_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $selected_view = altm_get_selected_plans_view();
    ?>
    <div class="wrap altm-plans-page">
        <?php altm_render_plans_page_content($selected_view, 'page'); ?>
    </div>
    <?php
}

/**
 * Determine whether shared plan assets are needed on this admin screen.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return bool
 */
function altm_should_enqueue_plans_assets($hook_suffix) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

    if (in_array($page, array('alt-magic-plans', 'alt-magic-bulk-generation', 'alt-magic-image-renaming'), true)) {
        return true;
    }

    return in_array($hook_suffix, array('upload.php', 'post.php', 'post-new.php'), true);
}

/**
 * Enqueue the shared plans page and exhausted-credit modal assets.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function altm_enqueue_plans_assets($hook_suffix) {
    if (!altm_should_enqueue_plans_assets($hook_suffix)) {
        return;
    }

    $asset_version = defined('ALT_MAGIC_PLUGIN_VERSION') ? ALT_MAGIC_PLUGIN_VERSION : '1.8.2';

    wp_enqueue_style(
        'altm-plans',
        plugin_dir_url(__FILE__) . '../css/altm-plans-page.css',
        array('dashicons'),
        $asset_version
    );

    wp_enqueue_script(
        'altm-plans',
        plugin_dir_url(__FILE__) . '../scripts/altm-plans-page-script.js',
        array(),
        $asset_version,
        true
    );

    wp_localize_script(
        'altm-plans',
        'altmPlansSettings',
        array(
            'plansPageUrl' => altm_get_plans_page_url('credits'),
            'defaultNoCreditsMessage' => __('No credits remaining.', 'alt-magic'),
        )
    );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing.
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    $modal_pages = array('alt-magic-bulk-generation', 'alt-magic-image-renaming');

    if (in_array($page, $modal_pages, true) || in_array($hook_suffix, array('upload.php', 'post.php', 'post-new.php'), true)) {
        $GLOBALS['altm_render_plans_modal'] = true;
    }
}
add_action('admin_enqueue_scripts', 'altm_enqueue_plans_assets');

/**
 * Render the exhausted-credit plans modal once on supported admin screens.
 *
 * @return void
 */
function altm_render_plans_footer_modal() {
    if (empty($GLOBALS['altm_render_plans_modal'])) {
        return;
    }
    ?>
    <div id="altm-plans-modal" class="altm-plans-modal" hidden aria-hidden="true">
        <div class="altm-plans-modal__backdrop" data-altm-plans-close></div>
        <div class="altm-plans-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="altm-plans-modal-title">
            <header class="altm-plans-modal__header">
                <div>
                    <span class="altm-plans-modal__icon dashicons dashicons-money-alt" aria-hidden="true"></span>
                    <div class="altm-plans-modal__heading">
                        <h2 id="altm-plans-modal-title" class="altm-plans-modal__error-title" aria-live="assertive" aria-atomic="true"><?php echo esc_html__('No credits remaining.', 'alt-magic'); ?></h2>
                        <p><?php echo esc_html__('Choose a plan below to continue generating alt text.', 'alt-magic'); ?></p>
                    </div>
                </div>
                <button type="button" class="altm-plans-modal__close" data-altm-plans-close aria-label="<?php echo esc_attr__('Close plans', 'alt-magic'); ?>">&times;</button>
            </header>

            <div class="altm-plans-modal__content">
                <div class="altm-plans-page altm-plans-page--modal">
                    <?php altm_render_plans_page_content('credits', 'modal'); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
add_action('admin_footer', 'altm_render_plans_footer_modal');
