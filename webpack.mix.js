const mix = require('laravel-mix');

mix.options({
	processCssUrls: false
});

mix.js('assets/js/app.js', 'dist')
	.js('assets/js/stripe.js', 'dist')
	.sass('assets/scss/screen.scss', 'dist')
	.sass('assets/scss/stripe.scss', 'dist')
	.version()
	.setPublicPath('dist');

mix.extract();
