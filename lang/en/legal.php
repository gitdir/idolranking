<?php

return [
    'document_label' => 'IDOL RANKING legal information',
    'footer_navigation' => 'Legal documents',
    'footer_privacy' => 'Privacy Policy',
    'footer_terms' => 'Terms of Service',
    'effective_date_label' => 'Effective date',
    'effective_date_placeholder' => '[Operator to complete: YYYY-MM-DD]',
    'privacy_title' => 'Privacy Policy',
    'privacy_seo_title' => 'Privacy Policy | IdolRanking',
    'privacy_seo_description' => 'Learn how IdolRanking handles account data, Google sign-in, cookies, visitor analytics, public posts, and privacy requests.',
    'privacy_intro' => 'This policy explains the information IdolRanking processes to operate the service, why it is used, how long it is retained, which providers may process it, and how requests can be made. Bracketed items must be verified and completed by the operator before publication.',
    'privacy_sections' => [
        [
            'title' => '1. Operator and contact',
            'items' => [
                'Service operator: [Operator review required: legal entity or individual name and registration details]',
                'Business address: [Operator to complete: full postal address]',
                'Privacy lead or responsible contact: [Operator to complete: name, role, and contact method]',
                'Privacy contact email: help@idolranking.com — [Operator to confirm that this inbox is monitored and assign a handler]',
            ],
        ],
        [
            'title' => '2. Information processed and how it is collected',
            'paragraphs' => [
                'Email registration processes an email address, a password hash, a chosen nickname, a selected country code, email-verification status and time, and account timestamps. The service does not store the plaintext password. Referral attribution may also be stored when a member joins through a referral.',
                'If you choose Google sign-in, the service requests the openid, email, and profile scopes. It receives a Google account identifier and verified email address to link or create an account. A Google display name may be used as a temporary nickname suggestion during sign-up; it is not automatically published in place of the nickname you choose.',
                'The service processes member-submitted Fan Posts, comments, attendance messages, likes and recommendations, votes, idol/group registration requests, language choices, profile images, and public profile fields such as nickname, member code, country, and profile image to provide those features.',
                'For service operation and security, the application records request paths, User-Agent strings, member association, and timestamps. Public-page access logs do not store raw IP addresses, but login-attempt histories and password-reset request records do store IP addresses and User-Agent strings. Hosting or web-server logs may also contain IP addresses; their exact contents and retention depend on the hosting configuration: [Operator to verify actual logs and retention].',
                'Email verification and password recovery may process the relevant email address, verification state, a hashed expiring token, request time, and request IP. The plaintext password and token are not stored.',
            ],
        ],
        [
            'title' => '3. Purposes',
            'items' => [
                'Account registration and sign-in by email or Google, email verification, password recovery, and account security',
                'Providing and moderating profiles, Fan Posts, comments, attendance messages, likes, recommendations, and idol/group registration requests',
                'Administering Heart rewards and idol/group voting, detecting duplicate or abusive activity, and responding to support requests',
                'Measuring public-page visits and service reliability. Daily visitor statistics use the Asia/Seoul date boundary.',
                'Translating Fan Posts, comments, or attendance messages when a user requests translation, and caching the result for display',
                'Complying with law, reviewing rights reports, resolving disputes, and protecting system security',
            ],
        ],
        [
            'title' => '4. Cookies, sessions, and visitor analytics',
            'paragraphs' => [
                'The service uses a session cookie to maintain sign-in state, CSRF protection, language choice, and related request state. Session storage and expiration depend on the current server configuration. A separate cookie stores recent Fan Post view times to limit duplicate view counts; it lasts up to one hour.',
                'Visitor statistics use the first-party cookie idolranking_visitor, which stores a random identifier and currently has a configured lifetime of two years. UV counts an identifier once per day; PV counts eligible public HTML page navigations. Administrators, dummy accounts, and bots identified from User-Agent markers are excluded from the UV/PV totals. User-Agent classification is imperfect and cannot identify every automated request.',
                'The visitor identifier is not derived from an IP address. Separate login security records may contain IP addresses. Restricting cookies in your browser can affect sign-in, saved language, or the ability to distinguish unique visitors.',
            ],
        ],
        [
            'title' => '5. Third-party providers and international transfers',
            'paragraphs' => [
                'Google OAuth is used for Google account sign-in. When a user requests a translation, the text to be translated and language information are sent to the Google Cloud Translation API. If a Fan Post includes a YouTube video thumbnail, the browser may request the image directly from YouTube image servers.',
                'Verification and password-recovery email may be delivered through the email provider configured for production. The application and database are hosted in the Hostinger environment. The provider legal entities, data categories, locations, retention, subprocessors, and transfer safeguards must be completed from actual contracts and production settings: [Operator to complete provider, country, and safeguards]. External sites linked from the service are governed by their own privacy notices.',
            ],
        ],
        [
            'title' => '6. Retention and deletion',
            'paragraphs' => [
                'Account information and associated content are kept as needed to operate the account and maintain service records. The current application does not provide a self-service account-closure feature. The process for receiving and handling closure/deletion requests, related posts, Heart/vote/report records, and any legally retained records is [Operator to decide: scope, exceptions, deadlines, and responsible contact]. Records required by law or to protect other people may be retained separately or with restricted access under the applicable rules.',
                'The application is configured to delete public-page access-log rows after 90 days. No automatic cleanup was found for login histories, password-reset request IP records, or daily UV/PV aggregates and visitor identifiers; their exact retention periods and deletion jobs are [Operator to define per record type]. Translation caches may remain while the source content is available.',
                'Retention and disposal rules for rights-report evidence and uploaded files are [Operator to complete: retention by file type, authorized access, and disposal method].',
            ],
        ],
        [
            'title' => '7. Your rights and requests',
            'paragraphs' => [
                'Members can currently edit some profile information and their password in My Page. To request access, correction, deletion, restriction, or withdrawal of consent under applicable law, contact the privacy contact once the operator has confirmed the channel and process. We may verify identity and may limit a request where another person’s rights or a legal retention duty is affected.',
                'A self-service account-deletion feature and request-response deadline are not implemented in the current application. Before publication the operator must establish the real intake channel, identity checks, handling rules, and response time: [Operator action required].',
            ],
        ],
        [
            'title' => '8. Public content and profiles',
            'paragraphs' => [
                'Public posts, comments, and profile fields such as nickname, country, and profile image may be visible to logged-out visitors and search engines. Email addresses, password hashes, and sign-in/security records are not displayed on public profiles. After content is deleted or hidden, copies may remain temporarily in backups, rights-report evidence, caches, or records retained by law; the operator must define those periods.',
            ],
        ],
        [
            'title' => '9. Security measures',
            'paragraphs' => [
                'The service uses password hashing, request-forgery protection, session-ID regeneration after sign-in, authorization checks, and separate public/private file storage. The responsible security organization, incident-notification process, access-review schedule, and breach contact are [Operator to verify]. No internet service can eliminate every security risk.',
            ],
        ],
        [
            'title' => '10. Contact and policy changes',
            'paragraphs' => [
                'Privacy contact: help@idolranking.com ([Operator to confirm this is an official monitored address]). Do not send passwords or authentication tokens. Provide only the minimum account information needed to handle your request. The responsible contact, address, applicable law, response deadline, and change-notice process are [Operator to complete].',
                'When this policy changes, the service will update this page and its effective date. The operator must determine how advance notice and any legally required renewed consent will be handled: [Operator review required].',
            ],
        ],
    ],
    'terms_title' => 'Terms of Service',
    'terms_seo_title' => 'Terms of Service | IdolRanking',
    'terms_seo_description' => 'Read the rules for IdolRanking accounts, Fan Posts, comments, idol requests, Heart rewards, voting, and service use.',
    'terms_intro' => 'These terms describe the features currently provided by IdolRanking and the basic responsibilities of users and the operator. Legal and operational details in brackets must be finalized by the operator.',
    'terms_sections' => [
        [
            'title' => '1. Service and operator',
            'paragraphs' => [
                'IdolRanking is a community service that provides idol/group rankings, Fan Posts and comments, attendance messages, Heart rewards and voting, and idol/group registration requests.',
                'Operator: [Operator review required: legal entity or individual name]; address: [Operator to complete]; contact: help@idolranking.com ([Operator to confirm this is an official contact channel]).',
            ],
        ],
        [
            'title' => '2. Accounts and eligibility',
            'paragraphs' => [
                'Email registration requires email verification. Google registration uses a Google-verified email address and account-link identifier. Members must provide accurate information and protect their credentials. A Google sign-up member chooses their own nickname and country.',
                'Minimum age and guardian-consent requirements are [Operator to define for each applicable jurisdiction]. Do not represent the service as suitable for a specific age group until these rules are confirmed.',
            ],
        ],
        [
            'title' => '3. Member responsibilities and prohibited conduct',
            'items' => [
                'Using another person’s account or bypassing account security, authentication, access restrictions, or service controls',
                'Using automation, multiple accounts, or coordinated activity to manipulate Heart rewards, votes, rankings, or visitor statistics or obtain an improper benefit',
                'Posting illegal, fraudulent, threatening, harassing, hateful, defamatory, privacy-invasive, or targeted abusive material',
                'Submitting material that violates copyright, trademark, portrait rights, privacy, or a third-party service’s terms',
                'Excessive collection or scraping, probing for vulnerabilities, transmitting malicious code, or disrupting systems',
            ],
        ],
        [
            'title' => '4. Fan Posts, comments, and user content',
            'paragraphs' => [
                'Members must have the rights and permissions needed for Fan Posts, comments, attendance messages, images, and registration requests, and must respect the law and other people’s rights. The author retains their rights; posting does not transfer ownership to the operator.',
                'To operate the service, the member grants the operator a non-exclusive, service-limited permission to store and back up the content, convert it to necessary formats, transmit it, and display it to service users and visitors. The exact end of this permission and the duration of backup/report-evidence copies require [Operator legal review]. This text does not grant use for advertising or sale outside the service.',
                'An idol/group registration request is a proposal for review, not a promise of approval, registration time, or a particular reward. The operator may review, edit, or decline a request for accuracy, duplication, or rights concerns.',
            ],
        ],
        [
            'title' => '5. Copyright, portrait rights, reports, and moderation',
            'paragraphs' => [
                'Do not post another person’s work, photograph, likeness, or personal information without permission. The service provides reporting features for Fan Posts/comments and profile images. Include the specific content and reason for a report. The operator may review the material and hide, restore, or remove it, or request more information.',
                'Required legal-notice details, response deadlines, appeals, and repeat-infringer procedures are [Operator to complete under applicable law]. The implemented reporting tools do not claim to replace every statutory notice-and-takedown process.',
            ],
        ],
        [
            'title' => '6. Heart rewards and voting',
            'paragraphs' => [
                'Hearts are an in-service value used for currently implemented activity rewards and idol/group voting. They have no cash value, cannot be redeemed or transferred, and cannot currently be purchased. Reward types, ranges, daily limits, and eligibility follow the applicable feature and administrator settings; settings may change future reward conditions.',
                'Votes are processed under the current voting rules and available Heart balance. Rewards or votes obtained through automation, duplication, or false activity may be reviewed and corrected or invalidated. The notice and appeal process for erroneous rewards is [Operator review required: define handling criteria].',
            ],
        ],
        [
            'title' => '7. Content and account measures',
            'paragraphs' => [
                'For legal, rights-protection, security, or suspected-violation reasons, the operator may hide or remove posts, comments, images, or registration requests, adjust rewards, or restrict/suspend account access. Where practical, the operator will provide a reason and contact method; urgent security or legal cases may require immediate action.',
                'Suspension duration, appeal channel, member-requested account closure/deletion, and unused Heart handling are [Operator to finalize]. The current application does not provide a self-service account-deletion feature.',
            ],
        ],
        [
            'title' => '8. Service changes and availability',
            'paragraphs' => [
                'The operator may change or discontinue features, screens, reward settings, or content display for legal, security, technical, or operational reasons. Maintenance or outages may temporarily interrupt the service. External services such as Google, email providers, and YouTube are controlled by their respective providers.',
                'To the extent permitted by applicable law, responsibility may be limited for external failures, user conduct, or events outside the operator’s reasonable control. Nothing here limits mandatory consumer or privacy rights. Applicable law and final liability terms require [Operator legal review].',
            ],
        ],
        [
            'title' => '9. Changes and acceptance records',
            'paragraphs' => [
                'Changes will be posted on this page with their content and effective date. The operator must define advance notice, when changes take effect for existing members, and when renewed consent is required: [Operator to finalize]. The current application does not appear to record acceptance by policy version, so the operator must decide how acceptance will be presented and recorded.',
            ],
        ],
        [
            'title' => '10. Contact',
            'paragraphs' => [
                'For terms, content reports, or account restrictions: help@idolranking.com ([Operator to confirm the responsible person, monitored inbox, and response process]). The operator identity, business address, governing law, dispute venue, and response deadline must be completed before publication: [Operator action required].',
            ],
        ],
    ],
];
