const fs = require('fs');
const content = fs.readFileSync('admintest/dashboardtest.php', 'utf8');
const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gm;
let match;
let count = 0;
while ((match = scriptRegex.exec(content)) !== null) {
    count++;
    if (count === 6) {
        fs.writeFileSync('scratch/script6.js', match[1]);
        break;
    }
}
