const fs = require('fs');
let html = fs.readFileSync('index.html', 'utf8');

// The exact HTML from index.html for the video block
const oldVideoHTML = `<video autoplay loop muted playsinline>
                <source src="assets/video/into_video.mp4" type="video/mp4">
            </video>`;

const newVideoHTML = `<video autoplay loop muted playsinline class="video-blur">
                <source src="assets/video/into_video.mp4" type="video/mp4">
            </video>
            <video autoplay loop muted playsinline class="video-main">
                <source src="assets/video/into_video.mp4" type="video/mp4">
            </video>`;

html = html.replace(oldVideoHTML, newVideoHTML);

// The exact CSS from index.html
const oldCSS = `.hero-image-wrapper video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            opacity: 0.55;
            transition: opacity 0.5s ease;
        }`;

const newCSS = `.hero-image-wrapper video.video-blur {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: blur(25px);
            opacity: 0.4;
            transform: scale(1.1);
        }

        .hero-image-wrapper video.video-main {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            opacity: 0.85;
            transition: opacity 0.5s ease;
        }

        @media (max-width: 768px) {
            .hero-image-wrapper video.video-main {
                object-fit: cover;
            }
            .hero-image-wrapper video.video-blur {
                display: none;
            }
        }`;

html = html.replace(oldCSS, newCSS);
fs.writeFileSync('index.html', html);
console.log('Successfully updated video layout for vertical video!');
