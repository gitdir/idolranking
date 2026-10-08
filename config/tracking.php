<?php

return [
    'access_log_retention_days' => 90,
    'visitor_cookie_name' => 'idolranking_visitor',
    'visitor_cookie_minutes' => 60 * 24 * 365 * 2,
    // Central list for analytics exclusion only; matching traffic is never blocked.
    'bot_user_agent_markers' => [
        'bot', 'crawler', 'spider', 'slurp',
        'google-inspectiontool', 'googleother', 'adsbot-google',
        'bingpreview', 'yandex', 'baiduspider', 'duckduckgo-favicons',
        'facebookexternalhit', 'facebot', 'twitterbot', 'linkedinbot',
        'slackbot', 'discordbot', 'telegrambot', 'whatsapp', 'pinterest',
        'applebot', 'semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot',
        'lighthouse', 'pagespeed', 'pingdom', 'uptimerobot', 'statuscake',
        'headlesschrome', 'phantomjs', 'selenium', 'playwright', 'puppeteer',
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'httpclient',
        'libwww-perl', 'okhttp', 'postmanruntime', 'go-http-client',
    ],
];
