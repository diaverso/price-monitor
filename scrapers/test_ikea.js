const Hero = require("@ulixee/hero").default;

(async () => {
    const hero = await Hero.create({
        userAgent: "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        noChromeSandbox: true
    });

    await hero.goto("https://www.ikea.com/es/es/p/ekpurpurmal-funda-nordica-con-funda-almohada-blanco-azul-nube-40547003/");
    await hero.waitForPaintingStable();
    await hero.waitForMillis(3000);

    // Probar span[aria-hidden]
    console.error("=== Probando span[aria-hidden=true] ===");
    const ariaSpans = await hero.document.querySelectorAll("span[aria-hidden=true]");
    const count = await ariaSpans.length;
    console.error("Total encontrados:", count);
    
    for (let i = 0; i < Math.min(count, 10); i++) {
        const text = await ariaSpans[i].textContent;
        const className = await ariaSpans[i].className;
        if (text && (text.includes("€") || text.match(/[\d,]/))) {
            console.error();
        }
    }

    await hero.close();
})();
