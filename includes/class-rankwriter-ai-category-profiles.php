<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages user-defined Category Profiles via a private Custom Post Type.
 *
 * Each profile stores: niche description, target audience, target country,
 * article tone, monetization goal, preferred article structure,
 * banned topics/words, preferred keywords, custom prompt instructions,
 * default internal linking rules, default image style.
 */
class RankWriter_AI_Category_Profiles {

	const POST_TYPE       = 'rwai_category';
	const NONCE_KEY       = 'rwai_category_profile_nonce';
	const NONCE_ACT       = 'rwai_save_category_profile';
	const META_PRESET     = '_rwai_preset';
	const META_PRESET_KEY = '_rwai_preset_key';
	const META_WP_CAT_ID  = '_rwai_wp_category_id';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Category Profiles', 'rankwriter-ai' ),
					'singular_name' => __( 'Category Profile', 'rankwriter-ai' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Returns the canonical list of meta fields a profile holds.
	 * Each entry: key => array( label, type, sanitize_callback, default )
	 */
	public static function field_schema() {
		return array(
			'niche_description'  => array(
				'label'    => __( 'Niche Description', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => '',
			),
			'target_audience'    => array(
				'label'    => __( 'Target Audience', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => '',
			),
			'target_country'     => array(
				'label'    => __( 'Target Country', 'rankwriter-ai' ),
				'type'     => 'text',
				'sanitize' => 'sanitize_text_field',
				'default'  => '',
			),
			'article_tone'       => array(
				'label'    => __( 'Article Tone', 'rankwriter-ai' ),
				'type'     => 'select',
				'options'  => array(
					'professional'   => 'Professional',
					'conversational' => 'Conversational',
					'authoritative'  => 'Authoritative',
					'friendly'       => 'Friendly',
					'storytelling'   => 'Storytelling',
					'how-to'         => 'Instructional / How-to',
					'journalistic'   => 'Journalistic',
				),
				'sanitize' => 'sanitize_text_field',
				'default'  => 'professional',
			),
			'monetization_goal'  => array(
				'label'    => __( 'Monetization Goal', 'rankwriter-ai' ),
				'type'     => 'select',
				'options'  => array(
					'adsense'   => 'AdSense (high RPM topics)',
					'affiliate' => 'Affiliate Marketing',
					'leadgen'   => 'Lead Generation',
					'ecommerce' => 'E-commerce / Product Sales',
					'mixed'     => 'Mixed Monetization',
					'none'      => 'No Monetization (info only)',
				),
				'sanitize' => 'sanitize_text_field',
				'default'  => 'adsense',
			),
			'article_structure'  => array(
				'label'    => __( 'Preferred Article Structure', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => "1. Intro hook\n2. Quick answer / TL;DR\n3. H2 sections with H3 sub-points\n4. Bulleted lists & tables\n5. FAQs\n6. Conclusion + CTA",
			),
			'banned_terms'       => array(
				'label'    => __( 'Banned Topics / Words (comma-separated)', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => '',
			),
			'preferred_keywords' => array(
				'label'    => __( 'Preferred Keywords (comma-separated)', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => '',
			),
			'prompt_instructions' => array(
				'label'    => __( 'Custom Prompt Instructions', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => '',
			),
			'linking_rules'      => array(
				'label'    => __( 'Default Internal Linking Rules', 'rankwriter-ai' ),
				'type'     => 'textarea',
				'sanitize' => 'sanitize_textarea_field',
				'default'  => "- Link 3-5 relevant internal posts per article\n- Use descriptive anchor text (not 'click here')\n- Prioritize linking to category hub pages and top-performing posts",
			),
			'image_style'        => array(
				'label'    => __( 'Default Image Style', 'rankwriter-ai' ),
				'type'     => 'select',
				'options'  => array(
					'realistic'   => 'Realistic Photography',
					'illustration'=> 'Illustration / Vector',
					'infographic' => 'Infographic',
					'screenshot'  => 'Screenshot / Product Shot',
					'cinematic'   => 'Cinematic',
					'minimalist'  => 'Minimalist / Flat',
				),
				'sanitize' => 'sanitize_text_field',
				'default'  => 'realistic',
			),
		);
	}

	/**
	 * Built-in preset category profiles, seeded on first activation.
	 * Users can edit, delete, or extend these. "Restore default presets"
	 * re-seeds any presets the user has deleted.
	 *
	 * Smart defaults are chosen per niche so each preset is immediately
	 * usable — not an empty template.
	 *
	 * @return array<string, array> keyed by preset_key.
	 */
	public static function preset_definitions() {
		$LINK_DEFAULT = "- Link 3-5 relevant internal posts per article\n- Use descriptive anchor text (not 'click here')\n- Prioritize linking to category hub pages and top-performing posts";

		$presets = array(

			/* ------------------ FINANCE & MONEY ------------------ */
			'personal-finance' => array(
				'name'                => 'Personal Finance',
				'niche_description'   => 'Budgeting, saving, debt payoff, and money management for everyday people.',
				'target_audience'     => 'US adults 25-55 trying to take control of their money.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Hook with a relatable money problem\n2. Quick answer\n3. Step-by-step plan\n4. Real-number examples\n5. Tools / apps recommended\n6. FAQs",
				'preferred_keywords'  => 'budgeting, save money, pay off debt, emergency fund, money tips',
				'image_style'         => 'realistic',
			),
			'investing' => array(
				'name'                => 'Investing & Stocks',
				'niche_description'   => 'Stock market, ETFs, index funds, and long-term wealth building.',
				'target_audience'     => 'Beginning to intermediate retail investors.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Investment thesis or question\n2. Key data points\n3. Pros / Cons\n4. How to act on this\n5. FAQs\n6. Disclaimer",
				'preferred_keywords'  => 'how to invest, best stocks, index funds, brokerage, etf',
				'image_style'         => 'realistic',
			),
			'crypto' => array(
				'name'                => 'Cryptocurrency',
				'niche_description'   => 'Bitcoin, Ethereum, altcoins, DeFi, NFTs, and crypto news.',
				'target_audience'     => 'Crypto-curious retail readers.',
				'target_country'      => 'US',
				'article_tone'        => 'journalistic',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. News hook or coin overview\n2. Why it matters now\n3. Numbers / chart data\n4. Risks\n5. How to act\n6. Disclaimer",
				'preferred_keywords'  => 'bitcoin price, ethereum, defi, crypto wallet, altcoin',
				'banned_terms'        => 'guaranteed returns, 10x overnight',
				'image_style'         => 'cinematic',
			),
			'real-estate' => array(
				'name'                => 'Real Estate',
				'niche_description'   => 'Home buying, renting, investing, and property markets.',
				'target_audience'     => 'First-time buyers, renters, and small real-estate investors.',
				'target_country'      => 'US',
				'article_tone'        => 'professional',
				'monetization_goal'   => 'mixed',
				'article_structure'   => "1. Market context\n2. Specific advice with prices\n3. Step-by-step process\n4. Tools / calculators\n5. FAQs",
				'preferred_keywords'  => 'home buying, mortgage rates, real estate investing, rental, property',
				'image_style'         => 'realistic',
			),
			'insurance' => array(
				'name'                => 'Insurance',
				'niche_description'   => 'Health, auto, home, life, and travel insurance comparisons.',
				'target_audience'     => 'Consumers comparing policies.',
				'target_country'      => 'US',
				'article_tone'        => 'professional',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Quick coverage summary\n2. Comparison table\n3. Pros / Cons of each option\n4. How to apply\n5. FAQs",
				'preferred_keywords'  => 'best insurance, insurance quotes, coverage, policy, premium',
				'image_style'         => 'realistic',
			),
			'credit-cards-loans' => array(
				'name'                => 'Credit Cards & Loans',
				'niche_description'   => 'Credit card reviews, loans, mortgages, and credit-building strategies.',
				'target_audience'     => 'US adults building credit or comparing offers.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Best-for callout (best cashback, travel, etc.)\n2. Card / loan comparison table\n3. Eligibility + rates\n4. How to apply\n5. FAQs",
				'preferred_keywords'  => 'best credit cards, personal loan, credit score, apr, cashback',
				'image_style'         => 'realistic',
			),
			'make-money-online' => array(
				'name'                => 'Make Money Online',
				'niche_description'   => 'Side hustles, freelancing, online business, passive income.',
				'target_audience'     => 'People looking to earn extra income from home.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Earnings potential upfront\n2. Skills / tools needed\n3. Step-by-step starting guide\n4. Real income examples\n5. Pitfalls\n6. FAQs",
				'preferred_keywords'  => 'side hustle, work from home, passive income, online business, freelance',
				'banned_terms'        => 'get rich quick, guaranteed income, no work required',
				'image_style'         => 'realistic',
			),
			'tax-accounting' => array(
				'name'                => 'Tax & Accounting',
				'niche_description'   => 'Tax filing, deductions, business accounting, and IRS guidance.',
				'target_audience'     => 'Individual filers and small business owners.',
				'target_country'      => 'US',
				'article_tone'        => 'professional',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Quick answer to the tax question\n2. Detailed walkthrough\n3. Examples with numbers\n4. Forms / deadlines\n5. FAQs\n6. Disclaimer",
				'preferred_keywords'  => 'tax deductions, irs, file taxes, tax refund, accounting',
				'image_style'         => 'realistic',
			),

			/* ------------------ EDUCATION & CAREER ------------------ */
			'scholarships' => array(
				'name'                => 'Scholarships',
				'niche_description'   => 'University scholarships, grants, and study-abroad funding.',
				'target_audience'     => 'Students seeking funding for higher education.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. Scholarship name + amount upfront\n2. Eligibility checklist\n3. Application steps\n4. Deadline\n5. Tips to win\n6. FAQs",
				'preferred_keywords'  => 'scholarship, fully funded, application, deadline, study abroad',
				'image_style'         => 'realistic',
			),
			'visa-sponsorship-jobs' => array(
				'name'                => 'Visa Sponsorship Jobs',
				'niche_description'   => 'Companies offering visa sponsorship for foreign workers — salary ranges, application steps, and FAQs.',
				'target_audience'     => 'International job seekers targeting US/UK/Canada/Australia.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. Role + salary range upfront\n2. List of sponsoring companies\n3. Eligibility + visa type\n4. Application steps\n5. Resume / interview tips\n6. FAQs",
				'preferred_keywords'  => 'visa sponsorship, h1b jobs, work abroad, sponsor visa, foreign workers',
				'image_style'         => 'realistic',
			),
			'immigration' => array(
				'name'                => 'Immigration',
				'niche_description'   => 'Visa types, green-card processes, citizenship, and immigration news.',
				'target_audience'     => 'Prospective immigrants and visa holders.',
				'target_country'      => 'US',
				'article_tone'        => 'professional',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. Visa / process overview\n2. Eligibility requirements\n3. Step-by-step application\n4. Costs + timelines\n5. Common pitfalls\n6. FAQs",
				'preferred_keywords'  => 'green card, visa, immigration, citizenship, work permit',
				'image_style'         => 'realistic',
			),
			'government-grants' => array(
				'name'                => 'Government Grants',
				'niche_description'   => 'Federal and state grants for individuals, small businesses, and nonprofits.',
				'target_audience'     => 'Grant seekers across sectors.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. Grant name + amount\n2. Who qualifies\n3. Application steps\n4. Deadlines\n5. Sample success stories\n6. FAQs",
				'preferred_keywords'  => 'government grants, federal grant, small business grant, free money, apply for grant',
				'banned_terms'        => 'free money guaranteed, easy grant approval',
				'image_style'         => 'realistic',
			),
			'agriculture-grants' => array(
				'name'                => 'Agriculture Grants',
				'niche_description'   => 'Funding programs for farmers, ranchers, and agribusinesses (USDA, state, and private).',
				'target_audience'     => 'Farmers and ag entrepreneurs.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. Grant + funding amount\n2. Eligibility (acreage, crop type, producer status)\n3. Application steps\n4. Deadlines\n5. Documents needed\n6. FAQs",
				'preferred_keywords'  => 'usda grant, farm grant, agriculture funding, beginning farmer grant, rural grant',
				'image_style'         => 'realistic',
			),
			'jobs-careers' => array(
				'name'                => 'Jobs & Careers',
				'niche_description'   => 'Resume tips, interview prep, salary negotiation, and career growth.',
				'target_audience'     => 'Active job seekers and career switchers.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'mixed',
				'article_structure'   => "1. Specific career question\n2. Step-by-step solution\n3. Template / example\n4. Common mistakes\n5. FAQs",
				'preferred_keywords'  => 'resume tips, interview questions, career change, salary negotiation, linkedin',
				'image_style'         => 'realistic',
			),
			'remote-work' => array(
				'name'                => 'Remote Work',
				'niche_description'   => 'Remote jobs, work-from-home setup, productivity, and digital nomad lifestyle.',
				'target_audience'     => 'Remote workers and aspiring nomads.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'mixed',
				'article_structure'   => "1. Real remote-work scenario\n2. Practical solution\n3. Tools / gear list\n4. Tips from experience\n5. FAQs",
				'preferred_keywords'  => 'remote jobs, work from home, digital nomad, remote work setup, async work',
				'image_style'         => 'realistic',
			),
			'online-courses' => array(
				'name'                => 'Online Courses & Learning',
				'niche_description'   => 'Course reviews, learning platforms, and skill-building resources.',
				'target_audience'     => 'Adult learners and career changers.',
				'target_country'      => 'US',
				'article_tone'        => 'professional',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Course / platform overview\n2. What you learn\n3. Pricing comparison\n4. Pros / Cons\n5. Who it's for\n6. FAQs",
				'preferred_keywords'  => 'online course, coursera, udemy, learn online, certification',
				'image_style'         => 'realistic',
			),

			/* ------------------ HEALTH & LIFESTYLE ------------------ */
			'health-wellness' => array(
				'name'                => 'Health & Wellness',
				'niche_description'   => 'General health, preventative care, and wellness habits.',
				'target_audience'     => 'Health-conscious adults.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Health question or symptom\n2. What science says\n3. Actionable tips\n4. When to see a doctor\n5. FAQs\n6. Medical disclaimer",
				'preferred_keywords'  => 'health tips, wellness, healthy living, prevention, lifestyle',
				'banned_terms'        => 'cure, miracle, guaranteed weight loss',
				'image_style'         => 'realistic',
			),
			'fitness' => array(
				'name'                => 'Fitness & Workout',
				'niche_description'   => 'Workout routines, strength training, cardio, home gym, and athletic performance.',
				'target_audience'     => 'Fitness enthusiasts from beginners to advanced.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Workout goal\n2. The routine (sets / reps / time)\n3. Form tips\n4. Progression plan\n5. Equipment\n6. FAQs",
				'preferred_keywords'  => 'workout, strength training, home gym, fitness routine, exercise',
				'image_style'         => 'realistic',
			),
			'nutrition-diet' => array(
				'name'                => 'Nutrition & Diet',
				'niche_description'   => 'Meal plans, macros, diets (keto, paleo, etc.), and food science.',
				'target_audience'     => 'People managing weight or improving diet.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Diet question\n2. The science\n3. Sample meal plan\n4. Foods to eat / avoid\n5. FAQs\n6. Disclaimer",
				'preferred_keywords'  => 'meal plan, keto diet, intermittent fasting, macros, nutrition',
				'banned_terms'        => 'guaranteed weight loss, miracle diet',
				'image_style'         => 'realistic',
			),
			'mental-health' => array(
				'name'                => 'Mental Health',
				'niche_description'   => 'Anxiety, depression, stress, therapy, and mindfulness.',
				'target_audience'     => 'Adults navigating mental health challenges.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Compassionate intro\n2. What you might be feeling\n3. Coping strategies\n4. When to seek professional help\n5. Resources hotlines\n6. Disclaimer",
				'preferred_keywords'  => 'anxiety, depression, mindfulness, mental health tips, therapy',
				'banned_terms'        => 'cure depression, self-harm methods',
				'image_style'         => 'minimalist',
			),
			'beauty-skincare' => array(
				'name'                => 'Beauty & Skincare',
				'niche_description'   => 'Skincare routines, product reviews, and makeup tutorials.',
				'target_audience'     => 'Beauty enthusiasts.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Beauty problem\n2. The solution / routine\n3. Product picks\n4. How to apply\n5. Results timeline\n6. FAQs",
				'preferred_keywords'  => 'skincare routine, best moisturizer, makeup, anti-aging, acne treatment',
				'image_style'         => 'cinematic',
			),
			'fashion' => array(
				'name'                => 'Fashion & Style',
				'niche_description'   => 'Outfit ideas, trends, and style guides.',
				'target_audience'     => 'Fashion-aware shoppers.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Style theme / occasion\n2. Outfit breakdown\n3. Where to buy\n4. Styling tips\n5. FAQs",
				'preferred_keywords'  => 'outfit ideas, fashion trends, style guide, capsule wardrobe, what to wear',
				'image_style'         => 'cinematic',
			),
			'parenting' => array(
				'name'                => 'Parenting',
				'niche_description'   => 'Baby care, toddler tips, school-age advice, and parenting strategies.',
				'target_audience'     => 'Parents of children 0-12.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Relatable parenting scenario\n2. Expert-backed advice\n3. Step-by-step approach\n4. Product picks if relevant\n5. FAQs",
				'preferred_keywords'  => 'parenting tips, baby care, toddler, raising kids, family',
				'image_style'         => 'realistic',
			),
			'pet-care' => array(
				'name'                => 'Pet Care',
				'niche_description'   => 'Dog and cat care, training, nutrition, and product recommendations with emotional storytelling.',
				'target_audience'     => 'Pet parents.',
				'target_country'      => 'US',
				'article_tone'        => 'storytelling',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Emotional pet-owner moment\n2. Practical tips\n3. Product recommendations\n4. Vet guidance\n5. FAQs",
				'preferred_keywords'  => 'dog training, cat care, pet food, puppy tips, pet health',
				'image_style'         => 'realistic',
			),
			'relationships' => array(
				'name'                => 'Relationships & Dating',
				'niche_description'   => 'Dating advice, marriage, breakups, and relationship growth.',
				'target_audience'     => 'Adults navigating relationships.',
				'target_country'      => 'US',
				'article_tone'        => 'storytelling',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Real-life scenario\n2. Insight or framework\n3. What to do (and not do)\n4. Examples\n5. FAQs",
				'preferred_keywords'  => 'dating advice, relationship, marriage, breakup, communication',
				'image_style'         => 'cinematic',
			),

			/* ------------------ TECH & BUSINESS ------------------ */
			'technology' => array(
				'name'                => 'Technology News',
				'niche_description'   => 'Tech industry news, product launches, and analysis.',
				'target_audience'     => 'Tech-savvy readers.',
				'target_country'      => 'US',
				'article_tone'        => 'journalistic',
				'monetization_goal'   => 'mixed',
				'article_structure'   => "1. News hook\n2. What happened\n3. Why it matters\n4. Implications\n5. What's next",
				'preferred_keywords'  => 'tech news, ai, apple, google, startup',
				'image_style'         => 'cinematic',
			),
			'software-saas' => array(
				'name'                => 'Software & SaaS Reviews',
				'niche_description'   => 'Tool comparisons, software reviews, and SaaS roundups.',
				'target_audience'     => 'Business buyers and prosumers.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Best-for callout\n2. Feature comparison table\n3. Pricing\n4. Pros / Cons\n5. Verdict\n6. FAQs",
				'preferred_keywords'  => 'best software, saas tools, software review, alternatives, comparison',
				'image_style'         => 'screenshot',
			),
			'gadgets' => array(
				'name'                => 'Gadgets & Electronics',
				'niche_description'   => 'Phones, laptops, headphones, and consumer electronics reviews.',
				'target_audience'     => 'Tech buyers.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Product overview\n2. Specs table\n3. Real-world testing\n4. Pros / Cons\n5. Who should buy it\n6. FAQs",
				'preferred_keywords'  => 'best laptop, phone review, headphones, gadgets, electronics',
				'image_style'         => 'screenshot',
			),
			'web-development' => array(
				'name'                => 'Web Development',
				'niche_description'   => 'Tutorials, frameworks, code samples, and developer tools.',
				'target_audience'     => 'Junior to mid-level web developers.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Problem statement\n2. Step-by-step solution with code\n3. Common gotchas\n4. Related tools\n5. FAQs",
				'preferred_keywords'  => 'javascript, react, tutorial, web development, coding',
				'image_style'         => 'screenshot',
			),
			'seo-marketing' => array(
				'name'                => 'SEO & Digital Marketing',
				'niche_description'   => 'SEO strategies, content marketing, ads, and analytics.',
				'target_audience'     => 'Marketers and site owners.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Marketing question\n2. Strategy / framework\n3. Step-by-step execution\n4. Tools recommended\n5. Examples\n6. FAQs",
				'preferred_keywords'  => 'seo tips, keyword research, content marketing, google ads, analytics',
				'image_style'         => 'infographic',
			),
			'business-entrepreneurship' => array(
				'name'                => 'Business & Entrepreneurship',
				'niche_description'   => 'Starting and growing businesses, leadership, and strategy.',
				'target_audience'     => 'Founders and small business owners.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Business challenge\n2. Framework or playbook\n3. Examples / case studies\n4. Action steps\n5. FAQs",
				'preferred_keywords'  => 'startup, small business, entrepreneur, business growth, leadership',
				'image_style'         => 'realistic',
			),

			/* ------------------ TRAVEL & HOME ------------------ */
			'travel' => array(
				'name'                => 'Travel',
				'niche_description'   => 'Destination guides, travel tips, and trip planning.',
				'target_audience'     => 'Travelers and trip planners.',
				'target_country'      => 'US',
				'article_tone'        => 'storytelling',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Destination intro / hook\n2. When to go + cost\n3. Itinerary / things to do\n4. Where to stay + eat\n5. Practical tips\n6. FAQs",
				'preferred_keywords'  => 'travel guide, best places to visit, itinerary, things to do, travel tips',
				'image_style'         => 'cinematic',
			),
			'food-recipes' => array(
				'name'                => 'Food & Recipes',
				'niche_description'   => 'Recipes, meal prep, cooking tips, and food culture.',
				'target_audience'     => 'Home cooks.',
				'target_country'      => 'US',
				'article_tone'        => 'storytelling',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Recipe intro / story\n2. Ingredients\n3. Step-by-step instructions\n4. Tips / variations\n5. Storage / serving\n6. FAQs",
				'preferred_keywords'  => 'easy recipe, dinner ideas, meal prep, cooking, food',
				'image_style'         => 'cinematic',
			),
			'home-improvement' => array(
				'name'                => 'Home Improvement',
				'niche_description'   => 'DIY home projects, renovations, and household tips.',
				'target_audience'     => 'Homeowners and DIYers.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Project goal\n2. Tools / materials list\n3. Step-by-step instructions\n4. Cost breakdown\n5. Common mistakes\n6. FAQs",
				'preferred_keywords'  => 'diy home, home renovation, home improvement, fix it, household',
				'image_style'         => 'realistic',
			),
			'gardening' => array(
				'name'                => 'Gardening',
				'niche_description'   => 'Vegetable gardens, flower beds, indoor plants, and lawn care.',
				'target_audience'     => 'Backyard gardeners.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Plant / project overview\n2. Growing requirements\n3. Step-by-step\n4. Common problems\n5. Harvest / results\n6. FAQs",
				'preferred_keywords'  => 'how to grow, garden tips, plant care, vegetable garden, indoor plants',
				'image_style'         => 'realistic',
			),
			'diy-crafts' => array(
				'name'                => 'DIY & Crafts',
				'niche_description'   => 'Craft projects, handmade gifts, and creative DIY.',
				'target_audience'     => 'Crafters and hobbyists.',
				'target_country'      => 'US',
				'article_tone'        => 'friendly',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Project intro\n2. Materials\n3. Step-by-step\n4. Tips / variations\n5. Photos / results\n6. FAQs",
				'preferred_keywords'  => 'diy project, craft idea, handmade, easy craft, kids craft',
				'image_style'         => 'realistic',
			),

			/* ------------------ ENTERTAINMENT & HOBBIES ------------------ */
			'gaming' => array(
				'name'                => 'Gaming',
				'niche_description'   => 'Game reviews, guides, news, and esports.',
				'target_audience'     => 'Gamers across platforms.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Game / topic hook\n2. Walkthrough / review\n3. Tips / tricks\n4. Pros / Cons\n5. FAQs",
				'preferred_keywords'  => 'game guide, walkthrough, best games, gaming tips, esports',
				'image_style'         => 'screenshot',
			),
			'sports' => array(
				'name'                => 'Sports',
				'niche_description'   => 'Sports news, analysis, and athlete profiles.',
				'target_audience'     => 'Sports fans.',
				'target_country'      => 'US',
				'article_tone'        => 'journalistic',
				'monetization_goal'   => 'adsense',
				'article_structure'   => "1. News hook\n2. What happened\n3. Stats / analysis\n4. What's next",
				'preferred_keywords'  => 'nba, nfl, soccer, sports news, athlete',
				'image_style'         => 'cinematic',
			),
			'books-reading' => array(
				'name'                => 'Books & Reading',
				'niche_description'   => 'Book reviews, reading lists, and author spotlights.',
				'target_audience'     => 'Readers and book club members.',
				'target_country'      => 'US',
				'article_tone'        => 'conversational',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Book / theme intro\n2. Summary (no spoilers)\n3. What makes it great\n4. Who it's for\n5. Similar reads",
				'preferred_keywords'  => 'best books, book review, reading list, must read, book recommendations',
				'image_style'         => 'minimalist',
			),
			'photography' => array(
				'name'                => 'Photography',
				'niche_description'   => 'Camera reviews, tutorials, and editing tips.',
				'target_audience'     => 'Amateur and enthusiast photographers.',
				'target_country'      => 'US',
				'article_tone'        => 'how-to',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Photography concept\n2. Step-by-step technique\n3. Gear used\n4. Example shots\n5. Tips\n6. FAQs",
				'preferred_keywords'  => 'camera, photography tips, lens, lightroom, photo editing',
				'image_style'         => 'cinematic',
			),
			'productivity' => array(
				'name'                => 'Productivity',
				'niche_description'   => 'Time management, focus, habits, and workflow systems.',
				'target_audience'     => 'Knowledge workers and self-improvers.',
				'target_country'      => 'US',
				'article_tone'        => 'authoritative',
				'monetization_goal'   => 'affiliate',
				'article_structure'   => "1. Productivity problem\n2. Framework / system\n3. Step-by-step setup\n4. Tools used\n5. Results timeline\n6. FAQs",
				'preferred_keywords'  => 'productivity tips, time management, focus, habits, getting things done',
				'image_style'         => 'minimalist',
			),
		);

		// Apply the shared default linking rule to every preset.
		foreach ( $presets as $k => $p ) {
			if ( empty( $presets[ $k ]['linking_rules'] ) ) {
				$presets[ $k ]['linking_rules'] = $LINK_DEFAULT;
			}
			if ( ! isset( $presets[ $k ]['prompt_instructions'] ) ) {
				$presets[ $k ]['prompt_instructions'] = '';
			}
			if ( ! isset( $presets[ $k ]['banned_terms'] ) ) {
				$presets[ $k ]['banned_terms'] = '';
			}
		}

		return $presets;
	}

	/**
	 * Seed any presets that aren't already in the database.
	 *
	 * @param bool $force_all Re-insert every preset even if they already exist.
	 * @return int Number of presets created.
	 */
	public function seed_presets( $force_all = false ) {
		$presets = self::preset_definitions();
		$schema  = self::field_schema();
		$created = 0;

		foreach ( $presets as $key => $data ) {
			if ( ! $force_all ) {
				$existing = get_posts( array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => self::META_PRESET_KEY,
					'meta_value'     => $key,
					'fields'         => 'ids',
				) );
				if ( ! empty( $existing ) ) {
					continue;
				}
			}

			$post_id = wp_insert_post( array(
				'post_title'  => sanitize_text_field( $data['name'] ),
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
			), true );

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, self::META_PRESET, 1 );
			update_post_meta( $post_id, self::META_PRESET_KEY, $key );

			foreach ( $schema as $field_key => $cfg ) {
				$value = isset( $data[ $field_key ] ) ? $data[ $field_key ] : ( isset( $cfg['default'] ) ? $cfg['default'] : '' );
				if ( 'select' === $cfg['type'] && ! empty( $cfg['options'] ) && ! isset( $cfg['options'][ $value ] ) ) {
					$value = isset( $cfg['default'] ) ? $cfg['default'] : '';
				}
				update_post_meta( $post_id, '_rwai_' . $field_key, $value );
			}

			$created++;
		}

		return $created;
	}

	/**
	 * Save a profile from a $_POST payload. Handles both insert and update.
	 *
	 * @return int|WP_Error post ID on success.
	 */
	public function save_from_request( array $data ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwai_forbidden', __( 'Insufficient permissions.', 'rankwriter-ai' ) );
		}

		if ( empty( $data[ self::NONCE_KEY ] ) || ! wp_verify_nonce( $data[ self::NONCE_KEY ], self::NONCE_ACT ) ) {
			return new WP_Error( 'rwai_bad_nonce', __( 'Security check failed.', 'rankwriter-ai' ) );
		}

		$name = isset( $data['profile_name'] ) ? sanitize_text_field( wp_unslash( $data['profile_name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rwai_missing_name', __( 'Category name is required.', 'rankwriter-ai' ) );
		}

		$post_id = isset( $data['profile_id'] ) ? absint( $data['profile_id'] ) : 0;

		$post_arr = array(
			'post_title'  => $name,
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
		);

		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( ! $existing || self::POST_TYPE !== $existing->post_type ) {
				return new WP_Error( 'rwai_not_found', __( 'Profile not found.', 'rankwriter-ai' ) );
			}
			$post_arr['ID'] = $post_id;
			$result_id      = wp_update_post( $post_arr, true );
		} else {
			$result_id = wp_insert_post( $post_arr, true );
		}

		if ( is_wp_error( $result_id ) ) {
			return $result_id;
		}

		foreach ( self::field_schema() as $key => $cfg ) {
			$raw = isset( $data[ $key ] ) ? wp_unslash( $data[ $key ] ) : '';
			$cb  = isset( $cfg['sanitize'] ) ? $cfg['sanitize'] : 'sanitize_text_field';

			if ( 'select' === $cfg['type'] && ! empty( $cfg['options'] ) ) {
				$raw = isset( $cfg['options'][ $raw ] ) ? $raw : ( isset( $cfg['default'] ) ? $cfg['default'] : '' );
			} else {
				$raw = is_callable( $cb ) ? call_user_func( $cb, $raw ) : sanitize_text_field( $raw );
			}

			update_post_meta( $result_id, '_rwai_' . $key, $raw );
		}

		// WordPress category mapping: either an existing term ID, or empty
		// (in which case the generator falls back to auto-creating from name).
		$wp_cat_id = $this->resolve_wp_category_from_request( $data );
		update_post_meta( $result_id, self::META_WP_CAT_ID, (int) $wp_cat_id );

		return (int) $result_id;
	}

	/**
	 * Resolve the WP-category mapping from a $_POST payload:
	 *   - "wp_category_id" === "__new__" + "wp_category_new_name" → create new term
	 *   - "wp_category_id" is a positive int → use existing term
	 *   - otherwise 0 (use profile name as fallback at generation time)
	 */
	public function resolve_wp_category_from_request( array $data ) {
		$picker = isset( $data['wp_category_id'] ) ? trim( (string) $data['wp_category_id'] ) : '';
		if ( '__new__' === $picker ) {
			$new_name = isset( $data['wp_category_new_name'] ) ? sanitize_text_field( wp_unslash( $data['wp_category_new_name'] ) ) : '';
			if ( '' === $new_name ) {
				return 0;
			}
			$existing = get_term_by( 'name', $new_name, 'category' );
			if ( $existing && ! is_wp_error( $existing ) ) {
				return (int) $existing->term_id;
			}
			$created = wp_insert_term( $new_name, 'category' );
			if ( is_wp_error( $created ) ) {
				return 0;
			}
			return (int) $created['term_id'];
		}
		return absint( $picker );
	}

	public function delete( $post_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rwai_forbidden', __( 'Insufficient permissions.', 'rankwriter-ai' ) );
		}
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'rwai_not_found', __( 'Profile not found.', 'rankwriter-ai' ) );
		}
		return wp_delete_post( $post_id, true );
	}

	public function get( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$out = array(
			'id'              => $post->ID,
			'name'            => $post->post_title,
			'is_preset'       => (bool) get_post_meta( $post->ID, self::META_PRESET, true ),
			'preset_key'      => (string) get_post_meta( $post->ID, self::META_PRESET_KEY, true ),
			'wp_category_id'  => (int) get_post_meta( $post->ID, self::META_WP_CAT_ID, true ),
		);
		foreach ( self::field_schema() as $key => $cfg ) {
			$val = get_post_meta( $post->ID, '_rwai_' . $key, true );
			if ( '' === $val && isset( $cfg['default'] ) ) {
				$val = $cfg['default'];
			}
			$out[ $key ] = $val;
		}
		return $out;
	}

	public function get_all() {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = $this->get( $p->ID );
		}
		return $out;
	}

	public function count() {
		$counts = wp_count_posts( self::POST_TYPE );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Returns the profile as a structured prompt block ready to inject
	 * into a Claude system prompt.
	 */
	public function to_prompt_context( $post_id ) {
		$p = $this->get( $post_id );
		if ( ! $p ) {
			return '';
		}
		$schema = self::field_schema();
		$lines  = array();
		$lines[] = '## Category Profile: ' . $p['name'];
		foreach ( $schema as $key => $cfg ) {
			$val = isset( $p[ $key ] ) ? trim( (string) $p[ $key ] ) : '';
			if ( '' === $val ) {
				continue;
			}
			if ( 'select' === $cfg['type'] && isset( $cfg['options'][ $val ] ) ) {
				$val = $cfg['options'][ $val ];
			}
			$lines[] = '### ' . $cfg['label'];
			$lines[] = $val;
		}
		return implode( "\n", $lines );
	}
}
