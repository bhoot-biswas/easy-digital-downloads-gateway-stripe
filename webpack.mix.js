const mix = require('laravel-mix');

mix.js('assets/js/app.js', 'dist')
	.js('assets/js/stripe.js', 'dist')
	.sass('assets/scss/screen.scss', 'dist')
	.version()
	.setPublicPath('dist');

mix.extract();
