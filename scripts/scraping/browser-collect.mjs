import { chromium } from 'playwright';

const input = JSON.parse(await new Promise((resolve) => {
    let data = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => { data += chunk; });
    process.stdin.on('end', () => resolve(data));
}));

const target = new URL(input.url);
if (!['http:', 'https:'].includes(target.protocol) || target.hostname !== input.host || !/^\d{1,3}(?:\.\d{1,3}){3}$/.test(input.ip)) {
    throw new Error('허용되지 않는 브라우저 요청입니다.');
}

const browser = await chromium.launch({
    headless: true,
    channel: process.platform === 'win32' && !process.env.SCRAPER_CHROMIUM_PATH ? 'chrome' : undefined,
    executablePath: process.env.SCRAPER_CHROMIUM_PATH || undefined,
    args: ['--no-proxy-server', '--disable-background-networking', `--host-resolver-rules=MAP ${input.host} ${input.ip}`],
});

try {
    const context = await browser.newContext({ ignoreHTTPSErrors: false, serviceWorkers: 'block' });
    await context.route('**/*', (route) => {
        const requestUrl = new URL(route.request().url());
        if (requestUrl.hostname !== input.host || requestUrl.protocol !== target.protocol) return route.abort();
        if (['image', 'media', 'font'].includes(route.request().resourceType())) return route.abort();
        return route.continue();
    });
    const page = await context.newPage();
    const response = await page.goto(input.url, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForTimeout(1000);
    const html = await page.content();
    process.stdout.write(JSON.stringify({
        status: response?.status() ?? null,
        url: page.url(),
        title: await page.title(),
        html: Buffer.byteLength(html, 'utf8') <= input.maxBytes ? html : null,
        error: Buffer.byteLength(html, 'utf8') > input.maxBytes ? '렌더링된 HTML 크기가 제한을 초과했습니다.' : null,
    }));
} finally {
    await browser.close();
}
