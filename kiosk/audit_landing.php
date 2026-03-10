<?php
include 'header.php';
include 'functions.php';
?>

<main>
    <div class="audit-hero">
        <h1 class="text-4xl font-bold text-luxury-black">E-commerce Speed & SEO Audit</h1>
        <p class="text-xl text-luxury-gray mt-2">Leverage expert technical analysis to boost your store's performance and organic traffic.</p>
    </div>

    <div class="container audit-content">
        <h2 class="text-2xl font-bold text-luxury-black">Is Your Store Underperforming?</h2>
        <p class="mt-4 text-luxury-gray">
            Slow load times, poor Google rankings, and a high bounce rate are silent revenue killers. Based on the proven optimization strategies used to build this high-performance e-commerce site, I offer a premium, text-based auditing service to identify your store's critical bottlenecks.
        </p>

        <h3 class="text-xl font-bold text-luxury-black mt-8">What You'll Get in Your PDF Report:</h3>
        <ul class="feature-list text-luxury-gray space-y-2 mt-4 ml-6 list-disc">
            <li>A detailed analysis of your site's PageSpeed Insights score and actionable steps to improve it.</li>
            <li>Identification of database query bottlenecks that slow down your pages.</li>
            <li>An audit of your image assets with a clear plan for optimization.</li>
            <li>A review of your caching strategy (or lack thereof) and implementation guidance.</li>
            <li>Technical SEO checks to ensure your site is perfectly configured for search engines.</li>
        </ul>

        <div class="contact-form mt-12 bg-gray-50 p-8 rounded-lg shadow-sm border border-gray-100">
            <h2 class="text-2xl font-bold text-center text-luxury-black">Request an Audit</h2>
            <p class="text-center text-luxury-gray mt-2">Fill out the form below to get a flat-rate quote for your comprehensive site audit.</p>
            <form action="#" method="POST" class="mt-6 space-y-4 max-w-lg mx-auto">
                <div>
                    <label for="name" class="sr-only">Your Name</label>
                    <input type="text" name="name" id="name" class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Your Name" required>
                </div>
                <div>
                    <label for="email" class="sr-only">Your Email</label>
                    <input type="email" name="email" id="email" class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Your Email" required>
                </div>
                <div>
                    <label for="website" class="sr-only">Your Website URL</label>
                    <input type="url" name="website" id="website" class="w-full border border-gray-300 p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Your Website URL" required>
                </div>
                <div class="pt-2">
                    <button type="submit" class="btn btn-primary w-full py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition">Get a Quote</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>