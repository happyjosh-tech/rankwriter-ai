<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Five starter programmatic templates seeded on plugin activation. Users
 * can edit, duplicate, delete, or extend any of them. Each preset includes:
 *
 *   - title_template + slug_template
 *   - 3-4 intro variants (different opening angles)
 *   - 5-7 sections with multiple heading variants each
 *   - 2-3 section-order permutations
 *   - 12-15 FAQ pool entries (4 picked per page)
 *   - 3 conclusion variants
 *
 * Combined, the variation matrix yields hundreds of distinct page shapes
 * before any entity-specific content is considered.
 */
class RankWriter_AI_PSE_Presets {

	public static function definitions() {
		return array(

			/* ====================== City + Profession Jobs ====================== */
			'jobs-city-profession' => array(
				'name'           => 'Jobs: city × profession',
				'description'    => 'Generates "Highest paying {profession} jobs in {city}" pages.',
				'title_template' => 'Highest Paying {profession} Jobs in {city}',
				'slug_template'  => 'highest-paying-{profession-slug}-jobs-in-{city-slug}',
				'intent'          => 'commercial',
				'variables'       => array(
					'profession'      => array( 'required' => true,  'type' => 'string' ),
					'profession-slug' => array( 'required' => false, 'type' => 'string' ),
					'city'            => array( 'required' => true,  'type' => 'string' ),
					'city-slug'       => array( 'required' => false, 'type' => 'string' ),
					'country'         => array( 'required' => true,  'type' => 'string', 'default' => 'United States' ),
					'currency'        => array( 'required' => false, 'type' => 'string', 'default' => 'USD' ),
				),
				'semantic_keywords' => 'salary, hourly wage, annual pay, top employers, hiring, career path, qualifications, experience, certification, benefits',
				'min_word_count' => 1400,
				'outline'        => array(
					'intro_variants' => array(
						'Open with a real first-week scenario for someone starting a {profession} role in {city} — what their first paycheck actually looks like and why most candidates underestimate their negotiating power.',
						'Open with the gap between national {profession} salary averages and what {city} employers actually pay — and the specific reason the gap exists in {city}.',
						'Open with one named {city} employer that pays top-of-market for {profession} and what that signals about the rest of the {city} market.',
					),
					'sections' => array(
						array(
							'name'     => 'salary_range',
							'headings' => array(
								'How Much {profession}s Earn in {city}',
								'{city} {profession} Salary Breakdown',
								'{profession} Pay in {city}: Entry to Senior',
							),
							'content_guide' => 'Concrete numbers in {currency}: entry-level, mid, senior. Hourly + annual. Reference the cost-of-living adjustment for {city}.',
						),
						array(
							'name'     => 'top_employers',
							'headings' => array(
								'Top Employers Hiring {profession}s in {city}',
								'Where to Apply: Leading {city} Employers',
								'Best Companies for {profession}s in {city}',
							),
							'content_guide' => 'Name 5-8 real employers operating in {city}. Mix large institutions, hospitals/schools/etc., and growing private employers.',
						),
						array(
							'name'     => 'requirements',
							'headings' => array(
								'Requirements to Work as a {profession} in {city}',
								'Qualifications {city} Employers Look For',
								'What You Need to Land a {profession} Job in {city}',
							),
							'content_guide' => 'Licensing, certifications, degree expectations, work experience years, soft skills, {country}-specific requirements.',
						),
						array(
							'name'     => 'how_to_apply',
							'headings' => array(
								'How to Land a {profession} Role in {city}',
								'Your {city} {profession} Application Playbook',
								'Step-by-Step Application Guide for {profession}s in {city}',
							),
							'content_guide' => 'Resume tweaks for {city} market, where to find job postings (specific platforms), networking angles, interview prep.',
						),
						array(
							'name'     => 'cost_of_living',
							'headings' => array(
								'Cost of Living: Is the {city} Salary Enough?',
								'{city} Salary vs Cost of Living for {profession}s',
								'Living on a {profession} Salary in {city}',
							),
							'content_guide' => 'Rent ranges, transit, utilities, take-home math. Be specific — single vs family scenarios.',
						),
					),
					'section_order_variants' => array(
						array( 'salary_range', 'top_employers', 'requirements', 'how_to_apply', 'cost_of_living' ),
						array( 'salary_range', 'cost_of_living', 'top_employers', 'requirements', 'how_to_apply' ),
						array( 'top_employers', 'salary_range', 'requirements', 'how_to_apply', 'cost_of_living' ),
					),
					'faq_pool' => array(
						'What is the average {profession} salary in {city}?',
						'Which {city} employer pays {profession}s the most?',
						'Do I need a license to work as a {profession} in {city}?',
						'How long does it take to get hired as a {profession} in {city}?',
						'Are there entry-level {profession} jobs in {city}?',
						'How does {city} {profession} pay compare to other {country} cities?',
						'What benefits do {city} {profession}s typically receive?',
						'Is there a {profession} shortage in {city}?',
						'Do {city} {profession}s get signing bonuses?',
						'Can I work remotely as a {profession} based in {city}?',
						'What career growth does a {profession} role in {city} offer?',
						'Which neighborhoods in {city} have the most {profession} jobs?',
					),
					'conclusion_variants' => array(
						'Close with the single highest-leverage action a new {profession} applicant in {city} should take this week.',
						'Close with a one-sentence salary-trend prediction for {profession}s in {city} over the next 12 months and what causes it.',
						'Close with the most common rejection reason {city} {profession} candidates hit and how to avoid it.',
					),
				),
			),

			/* ====================== Best Universities by Region ====================== */
			'universities-region' => array(
				'name'           => 'Universities: region × criteria',
				'description'    => 'Generates "Best universities in {region}" / "Cheapest universities in {region}" pages.',
				'title_template' => '{criteria} Universities in {region}',
				'slug_template'  => '{criteria-slug}-universities-in-{region-slug}',
				'intent'          => 'commercial',
				'variables'       => array(
					'criteria'      => array( 'required' => true, 'type' => 'string', 'default' => 'Best' ),
					'criteria-slug' => array( 'required' => false, 'type' => 'string', 'default' => 'best' ),
					'region'        => array( 'required' => true,  'type' => 'string' ),
					'region-slug'   => array( 'required' => false, 'type' => 'string' ),
					'country'       => array( 'required' => true,  'type' => 'string' ),
				),
				'semantic_keywords' => 'tuition, admissions, acceptance rate, rankings, undergraduate, graduate, scholarships, international students, campus, programs',
				'min_word_count' => 1600,
				'outline'        => array(
					'intro_variants' => array(
						'Open with a specific number: how many universities in {region} match the "{criteria}" criteria and why that gap exists.',
						'Open with one university in {region} most readers haven\'t heard of but that quietly outperforms the famous names on this criteria.',
						'Open with the single biggest mistake students make when shortlisting {criteria} universities in {region}.',
					),
					'sections' => array(
						array(
							'name'     => 'methodology',
							'headings' => array(
								'How We Ranked These {region} Universities',
								'Our Criteria for "{criteria}" in {region}',
								'Methodology: What "{criteria}" Means Here',
							),
							'content_guide' => 'Specific factors: tuition, acceptance rate, graduate outcomes, etc. Be honest about what the ranking excludes.',
						),
						array(
							'name'     => 'top_picks',
							'headings' => array(
								'Top {criteria} Universities in {region}',
								'The {region} Universities That Top the List',
								'{criteria} Universities to Apply To in {region}',
							),
							'content_guide' => 'List 7-10 named universities. Each: 2-3 sentences with concrete numbers (tuition, acceptance rate, notable programs).',
						),
						array(
							'name'     => 'tuition',
							'headings' => array(
								'Tuition and Fees at {region} Universities',
								'What These {region} Universities Actually Cost',
								'Pricing Breakdown: {region} Universities',
							),
							'content_guide' => 'Per-university tuition for domestic + international students. Mention financial aid availability.',
						),
						array(
							'name'     => 'admissions',
							'headings' => array(
								'How to Get Admitted to {region} Universities',
								'Admission Requirements at {region} Universities',
								'{region} University Application Process',
							),
							'content_guide' => 'GPA expectations, standardized tests, application timelines, deadlines.',
						),
						array(
							'name'     => 'international',
							'headings' => array(
								'International Students at {region} Universities',
								'Foreign Student Guide to {region} Universities',
								'Studying in {region} as an International Student',
							),
							'content_guide' => 'Visa requirements for {country}, English proficiency tests, support services, fees difference.',
						),
						array(
							'name'     => 'scholarships',
							'headings' => array(
								'Scholarships at {region} Universities',
								'Financial Aid Options at {region} Universities',
								'Funding Your {region} Degree',
							),
							'content_guide' => 'Named scholarships, government aid, merit awards.',
						),
					),
					'section_order_variants' => array(
						array( 'methodology', 'top_picks', 'tuition', 'admissions', 'international', 'scholarships' ),
						array( 'top_picks', 'tuition', 'scholarships', 'admissions', 'international', 'methodology' ),
						array( 'top_picks', 'admissions', 'tuition', 'international', 'scholarships', 'methodology' ),
					),
					'faq_pool' => array(
						'Which is the {criteria} university in {region}?',
						'How much does it cost to study at a {region} university?',
						'What are the admission requirements for {region} universities?',
						'Can international students apply to {region} universities?',
						'Are there scholarships at {region} universities?',
						'What is the acceptance rate at top {region} universities?',
						'How long does it take to complete a degree in {region}?',
						'Are {region} university degrees recognized internationally?',
						'What language are courses taught in at {region} universities?',
						'When are the application deadlines for {region} universities?',
						'Do {region} universities offer online programs?',
						'What is student life like at {region} universities?',
					),
					'conclusion_variants' => array(
						'Close with the next concrete action: which document to prepare this week for a {region} university application.',
						'Close with a single sharp opinion on which university on the list is most underrated and why.',
						'Close with a 60-day application planning checklist tailored to {region} timelines.',
					),
				),
			),

			/* ====================== Salary pages ====================== */
			'salary-role-country' => array(
				'name'           => 'Salary: role × country',
				'description'    => '"{role} salary in {country}" deep-dive pages.',
				'title_template' => '{role} Salary in {country}: Full {year} Breakdown',
				'slug_template'  => '{role-slug}-salary-in-{country-slug}-{year}',
				'intent'          => 'informational',
				'variables'       => array(
					'role'         => array( 'required' => true,  'type' => 'string' ),
					'role-slug'    => array( 'required' => false, 'type' => 'string' ),
					'country'      => array( 'required' => true,  'type' => 'string' ),
					'country-slug' => array( 'required' => false, 'type' => 'string' ),
					'currency'     => array( 'required' => false, 'type' => 'string', 'default' => 'USD' ),
					'year'         => array( 'required' => true,  'type' => 'string' ),
				),
				'semantic_keywords' => 'salary range, average pay, median, hourly rate, annual income, take-home, taxation, career levels, experience-based pay, benefits',
				'min_word_count' => 1500,
				'outline'        => array(
					'intro_variants' => array(
						'Open with one specific {role} in {country} who landed in the top 10% of pay and the single decision that got them there.',
						'Open with the median {role} salary in {country} for {year} — and why the average is meaningfully higher than the median.',
						'Open with the biggest {country}-specific salary myth about {role}s — and the real number.',
					),
					'sections' => array(
						array( 'name' => 'overview',       'headings' => array( 'Average {role} Salary in {country}', '{country} {role} Pay Overview', 'What {role}s Earn in {country}' ), 'content_guide' => 'Median, mean, 10th and 90th percentile. Annual and monthly.' ),
						array( 'name' => 'by_experience', 'headings' => array( '{role} Pay by Experience Level', 'How Salary Scales with Experience', '{role} Salary Progression in {country}' ), 'content_guide' => 'Entry (0-2y), mid (3-7y), senior (8+y). Concrete numbers in {currency}.' ),
						array( 'name' => 'by_region',     'headings' => array( 'Regional Differences Within {country}', '{role} Pay by City in {country}', 'Where {role}s Earn Most in {country}' ), 'content_guide' => 'Top 5 cities/regions with their salary deltas vs national average.' ),
						array( 'name' => 'taxes',         'headings' => array( 'After-Tax {role} Salary in {country}', 'Take-Home Math for {role}s in {country}', '{country} Taxes on {role} Income' ), 'content_guide' => 'Effective tax rate, deductions, take-home math.' ),
						array( 'name' => 'how_to_raise', 'headings' => array( 'How to Earn More as a {role} in {country}', 'Career Moves That Raise Your {role} Salary', '{country} Pay Negotiation Playbook for {role}s' ), 'content_guide' => 'Specific tactics: certifications, employer types, switching companies.' ),
					),
					'section_order_variants' => array(
						array( 'overview', 'by_experience', 'by_region', 'taxes', 'how_to_raise' ),
						array( 'overview', 'by_region', 'by_experience', 'how_to_raise', 'taxes' ),
						array( 'by_experience', 'overview', 'by_region', 'how_to_raise', 'taxes' ),
					),
					'faq_pool' => array(
						'What is the average {role} salary in {country} in {year}?',
						'How much does an entry-level {role} earn in {country}?',
						'Where do {role}s earn the most in {country}?',
						'How is {role} salary taxed in {country}?',
						'Is being a {role} a high-paying career in {country}?',
						'What benefits do {country} {role}s typically receive?',
						'How can I increase my {role} salary in {country}?',
						'Do {country} {role}s get bonuses?',
						'What is the {role} salary range in {country}?',
						'Has {role} pay grown in {country} over the last 5 years?',
						'Are remote {role} jobs in {country} paid differently?',
						'How does {country} {role} pay compare globally?',
					),
					'conclusion_variants' => array(
						'Close with a forward-looking sentence on where {role} salaries in {country} are heading in {year}+1 and the trigger that\'ll move them.',
						'Close with one specific certification or skill that produces the biggest salary jump for {role}s in {country}.',
						'Close with the most common pay-negotiation mistake {role}s in {country} make.',
					),
				),
			),

			/* ====================== Scholarships ====================== */
			'scholarships-demographic-country' => array(
				'name'           => 'Scholarships: demographic × country',
				'description'    => '"Scholarships for {demographic} in {country}" pages.',
				'title_template' => 'Scholarships for {demographic} in {country} ({year})',
				'slug_template'  => 'scholarships-for-{demographic-slug}-in-{country-slug}-{year}',
				'intent'          => 'transactional',
				'variables'       => array(
					'demographic'      => array( 'required' => true,  'type' => 'string' ),
					'demographic-slug' => array( 'required' => false, 'type' => 'string' ),
					'country'          => array( 'required' => true,  'type' => 'string' ),
					'country-slug'     => array( 'required' => false, 'type' => 'string' ),
					'year'             => array( 'required' => true,  'type' => 'string' ),
				),
				'semantic_keywords' => 'fully funded, partial scholarship, tuition waiver, stipend, application deadline, eligibility, GPA requirement, English proficiency, study permit',
				'min_word_count' => 1700,
				'outline'        => array(
					'intro_variants' => array(
						'Open with one named scholarship in {country} that 90% of {demographic} applicants overlook — and why it has the highest acceptance rate of any on this list.',
						'Open with the total dollar value of scholarships available to {demographic} in {country} this year and what percentage actually gets claimed.',
						'Open with the single document {demographic} applicants must get right before any scholarship in {country} will move past initial review.',
					),
					'sections' => array(
						array( 'name' => 'top_scholarships', 'headings' => array( 'Top Scholarships for {demographic} in {country}', '{country} Scholarships {demographic} Should Apply To', 'Best-Funded Scholarships for {demographic} Studying in {country}' ), 'content_guide' => 'List 6-10 named scholarships. Each: amount, deadline, eligibility, host institution, link/source.' ),
						array( 'name' => 'eligibility',     'headings' => array( 'Eligibility Requirements', 'Who Qualifies as {demographic} for These Scholarships', '{country} Scholarship Eligibility for {demographic}' ), 'content_guide' => 'GPA, English proficiency, country of origin, age limits, study level.' ),
						array( 'name' => 'application',     'headings' => array( 'How to Apply Step-by-Step', '{country} Scholarship Application Process for {demographic}', 'Your {year} Application Playbook' ), 'content_guide' => 'Documents, recommendation letters, SOP, deadlines, application portals.' ),
						array( 'name' => 'visa',            'headings' => array( '{country} Student Visa for {demographic}', 'Study Permit Requirements', 'Visa Path After Scholarship Approval' ), 'content_guide' => 'Visa type, processing time, supporting docs, embassy of {country}.' ),
						array( 'name' => 'common_mistakes', 'headings' => array( 'Common Rejection Reasons', 'Mistakes {demographic} Applicants Make', 'Why Scholarship Applications Fail' ), 'content_guide' => 'Generic personal statement, missed deadlines, weak references, incomplete docs.' ),
					),
					'section_order_variants' => array(
						array( 'top_scholarships', 'eligibility', 'application', 'visa', 'common_mistakes' ),
						array( 'top_scholarships', 'application', 'eligibility', 'common_mistakes', 'visa' ),
						array( 'eligibility', 'top_scholarships', 'application', 'visa', 'common_mistakes' ),
					),
					'faq_pool' => array(
						'Which scholarships in {country} are easiest for {demographic} to win?',
						'Are there fully funded scholarships in {country} for {demographic}?',
						'What GPA do I need to qualify as a {demographic} applicant?',
						'How long does the {country} scholarship application process take?',
						'Can I apply for multiple {country} scholarships at once?',
						'Do I need an English proficiency test to apply?',
						'What documents are required for {country} scholarships?',
						'When are {country} scholarship deadlines for {year}?',
						'Can scholarship money cover living expenses too?',
						'What happens after my {country} scholarship is approved?',
						'Can I work part-time while on a {country} scholarship?',
						'Are there age limits for {country} scholarships for {demographic}?',
					),
					'conclusion_variants' => array(
						'Close with a specific 30-day action plan for a {demographic} applicant who decides today they want a {country} scholarship.',
						'Close with the one most overlooked scholarship in {country} for {demographic} and why it deserves immediate attention.',
						'Close with a paragraph on what changes when {demographic} actually win these scholarships — typical post-acceptance experience.',
					),
				),
			),

			/* ====================== Visa Sponsorship ====================== */
			'visa-sponsorship-role-country' => array(
				'name'           => 'Visa sponsorship: role × country',
				'description'    => 'Companies / roles that sponsor visas in a country.',
				'title_template' => 'Visa Sponsorship {role} Jobs in {country} ({year})',
				'slug_template'  => 'visa-sponsorship-{role-slug}-jobs-in-{country-slug}-{year}',
				'intent'          => 'transactional',
				'variables'       => array(
					'role'         => array( 'required' => true,  'type' => 'string' ),
					'role-slug'    => array( 'required' => false, 'type' => 'string' ),
					'country'      => array( 'required' => true,  'type' => 'string' ),
					'country-slug' => array( 'required' => false, 'type' => 'string' ),
					'year'         => array( 'required' => true,  'type' => 'string' ),
				),
				'semantic_keywords' => 'visa sponsorship, work permit, employer-sponsored, immigration, salary, eligibility, application, employer list, in-demand roles',
				'min_word_count' => 1500,
				'outline'        => array(
					'intro_variants' => array(
						'Open with one named {country} employer that sponsors more {role} visas than any other and the typical timeline from application to landed visa.',
						'Open with the specific {country} visa type that {role} applicants succeed with most often and the one that almost always fails.',
						'Open with the salary floor {country} employers must meet to sponsor a {role} visa and why some pay well above it.',
					),
					'sections' => array(
						array( 'name' => 'top_sponsors',   'headings' => array( 'Top {country} Employers Sponsoring {role} Visas', 'Companies That Sponsor {role}s in {country}', 'Visa-Friendly {country} Employers for {role}s' ), 'content_guide' => 'List 6-10 named employers. Sector, size, sponsorship volume, salary range.' ),
						array( 'name' => 'visa_types',     'headings' => array( '{country} Visa Types for {role} Sponsorship', 'Which {country} Visa You Need as a {role}', 'Sponsored {role} Visa Options' ), 'content_guide' => 'Visa categories with eligibility, duration, path to permanent residency.' ),
						array( 'name' => 'salary',         'headings' => array( 'Sponsored {role} Salaries in {country}', 'What Visa-Sponsored {role}s Actually Earn', '{country} {role} Pay on a Sponsored Visa' ), 'content_guide' => 'Salary minimum required for sponsorship + typical real pay ranges.' ),
						array( 'name' => 'how_to_apply',   'headings' => array( 'How to Land a Sponsored {role} Job in {country}', 'Your Application Strategy', '{country} {role} Sponsorship Playbook' ), 'content_guide' => 'Resume tweaks, job boards (named platforms), networking, interview prep.' ),
						array( 'name' => 'common_pitfalls', 'headings' => array( 'Why {role} Visa Applications Fail', 'Common Sponsorship Mistakes', '{country} {role} Visa Pitfalls to Avoid' ), 'content_guide' => 'Generic resume, no employer match, weak portfolio, document gaps.' ),
					),
					'section_order_variants' => array(
						array( 'top_sponsors', 'visa_types', 'salary', 'how_to_apply', 'common_pitfalls' ),
						array( 'visa_types', 'top_sponsors', 'salary', 'common_pitfalls', 'how_to_apply' ),
						array( 'top_sponsors', 'salary', 'visa_types', 'how_to_apply', 'common_pitfalls' ),
					),
					'faq_pool' => array(
						'Which {country} companies sponsor {role} visas?',
						'How long does {country} {role} visa sponsorship take?',
						'What is the minimum salary for sponsorship?',
						'Can I apply for a sponsored {role} job from outside {country}?',
						'Do I need an existing visa to apply for sponsorship?',
						'Which {country} visa type is best for {role}s?',
						'Is there a quota for {role} sponsorships in {country}?',
						'Can a sponsored {role} bring family to {country}?',
						'How much does the {country} {role} sponsorship process cost?',
						'Can a sponsored visa lead to permanent residency?',
						'What documents are needed for a sponsored {role} application?',
						'What if my sponsoring employer terminates me?',
					),
					'conclusion_variants' => array(
						'Close with the single highest-leverage thing a {role} should do this week to start a {country} sponsorship pipeline.',
						'Close with a forward-looking note on {country} {role} sponsorship demand in {year}+1.',
						'Close with the resume change that produces the most inbound interest from {country} sponsoring employers.',
					),
				),
			),
		);
	}

	/**
	 * Seed presets that aren't already in the DB (matched by name).
	 *
	 * @return int Count of presets created.
	 */
	public static function seed( $force = false ) {
		if ( ! class_exists( 'RankWriter_AI_PSE_Manager' ) || ! RankWriter_AI_PSE_DB::ready() ) {
			return 0;
		}
		$manager  = new RankWriter_AI_PSE_Manager();
		$existing = array();
		foreach ( $manager->get_all_templates() as $t ) {
			$existing[ $t['slug'] ] = $t['id'];
		}
		$created = 0;
		foreach ( self::definitions() as $key => $def ) {
			$slug = sanitize_title( $key );
			if ( ! $force && isset( $existing[ $slug ] ) ) {
				continue;
			}
			$res = $manager->create_template( array(
				'name'              => $def['name'],
				'slug'              => $slug,
				'description'       => $def['description'],
				'title_template'    => $def['title_template'],
				'slug_template'     => $def['slug_template'],
				'intent'            => $def['intent'],
				'outline'           => $def['outline'],
				'variables'         => $def['variables'],
				'semantic_keywords' => $def['semantic_keywords'],
				'min_word_count'    => isset( $def['min_word_count'] ) ? $def['min_word_count'] : 1400,
			) );
			if ( ! is_wp_error( $res ) ) {
				$created++;
			}
		}
		return $created;
	}
}
