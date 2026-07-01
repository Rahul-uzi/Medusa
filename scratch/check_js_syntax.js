const fs = require('fs');
const content = fs.readFileSync('admintest/dashboardtest.php', 'utf8');
const scriptRegex = /<script\b[^>]*>([\s\S]*?)<\/script>/gm;
let match;
let count = 0;
while ((match = scriptRegex.exec(content)) !== null) {
    count++;
    try {
        new Function(match[1]);
    } catch (e) {
        console.error('Syntax error in script ' + count + ': ' + e.message);
        
        // Let's find exactly where by passing it to Acorn or just throwing
        const lines = match[1].split('\n');
        for (let i=0; i<lines.length; i++) {
            if (lines[i].includes('<')) {
                console.log(`Line ${i + 1}: ${lines[i]}`);
            }
        }
    }
}
