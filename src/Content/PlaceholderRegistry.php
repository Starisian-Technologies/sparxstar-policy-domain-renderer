<?php
/**
 * Placeholder registry — defines the complete v1 placeholder set.
 *
 * @package Starisian\Sparxstar\PolicyRenderer\Content
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\PolicyRenderer\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines every allowed placeholder, maps it to a profile or policy meta key,
 * and declares the escaping context to use during output.
 *
 * No placeholders outside this registry can be resolved. Dynamic request-based
 * substitution ({{GET:…}}, {{POST:…}}) is deliberately excluded.
 */
final class PlaceholderRegistry {

	// Escaping context constants.
	public const ESCAPE_HTML  = 'html';
	public const ESCAPE_URL   = 'url';
	public const ESCAPE_EMAIL = 'email';

	// Source constants.
	public const SOURCE_PROFILE = 'profile';
	public const SOURCE_POLICY  = 'policy';

	/**
	 * Returns the full placeholder definition map.
	 *
	 * Each entry is an associative array with keys:
	 *   label       string   Human-readable label shown in the admin reference screen.
	 *   description string   Short explanation.
	 *   meta_key    string   Post meta key that supplies the value.
	 *   escape      string   One of the ESCAPE_* constants.
	 *   required    bool     Whether a missing value should generate an admin warning.
	 *   source      string   'profile' = pulled from the resolved profile post;
	 *                        'policy'  = pulled from the current policy post.
	 *
	 * @return array<string, array{label: string, description: string, meta_key: string, escape: string, required: bool, source: string}>
	 */
	public function get_definitions(): array {
		return [

			// --- Identity ---

			'{{SITE_OWNER_LEGAL_NAME}}' => [
				'label'       => __( 'Site Owner Legal Name', 'sparxstar-policy-renderer' ),
				'description' => __( 'Registered legal entity name (e.g. Starisian Technologies Ltd).', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'legal_name',
				'escape'      => self::ESCAPE_HTML,
				'required'    => true,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_DISPLAY_NAME}}' => [
				'label'       => __( 'Site Owner Display Name', 'sparxstar-policy-renderer' ),
				'description' => __( 'Public-facing name used in headings and prose.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'display_name',
				'escape'      => self::ESCAPE_HTML,
				'required'    => true,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_BRAND_NAME}}' => [
				'label'       => __( 'Site Owner Brand Name', 'sparxstar-policy-renderer' ),
				'description' => __( 'Brand or product name (e.g. SPARXSTAR).', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'brand_name',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			// --- Address ---

			'{{SITE_OWNER_ADDRESS}}' => [
				'label'       => __( 'Site Owner Full Address', 'sparxstar-policy-renderer' ),
				'description' => __( 'Full mailing address as a single plain-text block.', 'sparxstar-policy-renderer' ),
				'meta_key'    => '_spx_computed_address',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_ADDRESS_LINE_1}}' => [
				'label'       => __( 'Address Line 1', 'sparxstar-policy-renderer' ),
				'description' => __( 'First line of the mailing address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'address_line_1',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_ADDRESS_LINE_2}}' => [
				'label'       => __( 'Address Line 2', 'sparxstar-policy-renderer' ),
				'description' => __( 'Second line of the mailing address (optional).', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'address_line_2',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_CITY}}' => [
				'label'       => __( 'City', 'sparxstar-policy-renderer' ),
				'description' => __( 'City in the mailing address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'city',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_STATE_REGION}}' => [
				'label'       => __( 'State / Region', 'sparxstar-policy-renderer' ),
				'description' => __( 'State or region in the mailing address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'state_region',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_POSTAL_CODE}}' => [
				'label'       => __( 'Postal Code', 'sparxstar-policy-renderer' ),
				'description' => __( 'Postal or ZIP code.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'postal_code',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_COUNTRY}}' => [
				'label'       => __( 'Country', 'sparxstar-policy-renderer' ),
				'description' => __( 'Country in the mailing address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'country',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			// --- Contact ---

			'{{SITE_OWNER_PHONE}}' => [
				'label'       => __( 'Site Owner Phone', 'sparxstar-policy-renderer' ),
				'description' => __( 'Primary contact telephone number.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'phone',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SITE_OWNER_EMAIL}}' => [
				'label'       => __( 'Site Owner Email', 'sparxstar-policy-renderer' ),
				'description' => __( 'Primary contact email address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'email',
				'escape'      => self::ESCAPE_EMAIL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SUPPORT_EMAIL}}' => [
				'label'       => __( 'Support Email', 'sparxstar-policy-renderer' ),
				'description' => __( 'Customer support email address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'support_email',
				'escape'      => self::ESCAPE_EMAIL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{LEGAL_EMAIL}}' => [
				'label'       => __( 'Legal Email', 'sparxstar-policy-renderer' ),
				'description' => __( 'Legal / compliance contact email.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'legal_email',
				'escape'      => self::ESCAPE_EMAIL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{PRIVACY_EMAIL}}' => [
				'label'       => __( 'Privacy Email', 'sparxstar-policy-renderer' ),
				'description' => __( 'Data protection / privacy contact email.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'privacy_email',
				'escape'      => self::ESCAPE_EMAIL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{ABUSE_EMAIL}}' => [
				'label'       => __( 'Abuse Email', 'sparxstar-policy-renderer' ),
				'description' => __( 'Abuse reporting email address.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'abuse_email',
				'escape'      => self::ESCAPE_EMAIL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			// --- Branding / URLs ---

			'{{WEBSITE_URL}}' => [
				'label'       => __( 'Website URL', 'sparxstar-policy-renderer' ),
				'description' => __( 'Primary public website URL.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'website_url',
				'escape'      => self::ESCAPE_URL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{SUPPORT_URL}}' => [
				'label'       => __( 'Support URL', 'sparxstar-policy-renderer' ),
				'description' => __( 'Support portal URL.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'support_url',
				'escape'      => self::ESCAPE_URL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{TERMS_URL}}' => [
				'label'       => __( 'Terms URL', 'sparxstar-policy-renderer' ),
				'description' => __( 'Terms of Service page URL.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'terms_url',
				'escape'      => self::ESCAPE_URL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{PRIVACY_URL}}' => [
				'label'       => __( 'Privacy URL', 'sparxstar-policy-renderer' ),
				'description' => __( 'Privacy Policy page URL.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'privacy_url',
				'escape'      => self::ESCAPE_URL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{REFUND_URL}}' => [
				'label'       => __( 'Refund Policy URL', 'sparxstar-policy-renderer' ),
				'description' => __( 'Refund Policy page URL.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'refund_url',
				'escape'      => self::ESCAPE_URL,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			// --- Legal / Compliance ---

			'{{GOVERNING_LAW}}' => [
				'label'       => __( 'Governing Law', 'sparxstar-policy-renderer' ),
				'description' => __( 'Jurisdiction whose laws govern the agreement (e.g. Laws of Ghana).', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'governing_law',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{JURISDICTION}}' => [
				'label'       => __( 'Jurisdiction', 'sparxstar-policy-renderer' ),
				'description' => __( 'Dispute resolution jurisdiction (e.g. Accra, Ghana).', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'jurisdiction',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			'{{BUSINESS_REGISTRATION_NUMBER}}' => [
				'label'       => __( 'Business Registration Number', 'sparxstar-policy-renderer' ),
				'description' => __( 'Official company or business registration number.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'business_registration_number',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_PROFILE,
			],

			// --- Policy versioning (pulled from the policy post itself) ---

			'{{POLICY_EFFECTIVE_DATE}}' => [
				'label'       => __( 'Policy Effective Date', 'sparxstar-policy-renderer' ),
				'description' => __( 'Date from which this policy version is in effect.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'spx_policy_effective_date',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_POLICY,
			],

			'{{POLICY_LAST_UPDATED_DATE}}' => [
				'label'       => __( 'Policy Last Updated Date', 'sparxstar-policy-renderer' ),
				'description' => __( 'Date on which this policy was last reviewed or updated.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'spx_policy_last_reviewed_date',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_POLICY,
			],

			'{{POLICY_VERSION}}' => [
				'label'       => __( 'Policy Version', 'sparxstar-policy-renderer' ),
				'description' => __( 'Version identifier for this policy document.', 'sparxstar-policy-renderer' ),
				'meta_key'    => 'spx_policy_version',
				'escape'      => self::ESCAPE_HTML,
				'required'    => false,
				'source'      => self::SOURCE_POLICY,
			],
		];
	}
}
