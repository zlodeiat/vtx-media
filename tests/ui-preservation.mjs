import assert from 'node:assert/strict';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {parse} from 'espree';
// Compare request contracts, independent of JSX/layout/formatting changes.
function requests(source){
 const tree=parse(source,{ecmaVersion:'latest',sourceType:'module',ecmaFeatures:{jsx:true}}),found=[];
 const clean=v=>Array.isArray(v)?v.map(clean):v&&typeof v==='object'?Object.fromEntries(Object.entries(v).filter(([k])=>!['start','end','range','loc','raw'].includes(k)).map(([k,x])=>[k,clean(x)])):v;
 function walk(n){if(!n||typeof n!=='object')return;if(n.type==='CallExpression'&&n.callee.type==='Identifier'&&n.callee.name==='api')found.push(JSON.stringify(clean(n.arguments)));for(const v of Object.values(n))if(Array.isArray(v))v.forEach(walk);else if(v&&typeof v==='object')walk(v);}
 walk(tree);return found.sort();
}
for(const [root,slug,version,files] of [
 ['.','vtx-media','1.4.0',['assets/src/audit/AuditScreen.jsx','assets/src/audit/ScanPanel.jsx']],
 ['../vtx-media-pro','vtx-media-pro','1.6.1',['assets/src/cleanup.jsx','assets/src/ai.jsx','assets/src/intelligence.jsx','assets/src/Sources.jsx','assets/src/Scan.jsx','assets/src/usage.jsx','assets/src/optimizer/Screen.jsx','assets/src/optimizer/Diagnostics.jsx']]
])for(const file of files){
 const previous=execFileSync('unzip',['-p',`${root}/release/${slug}-${version}.zip`,`${slug}/${file}`],{encoding:'utf8'});
 assert.deepEqual(requests(fs.readFileSync(`${root}/${file}`,'utf8')),requests(previous),`${file}: request arguments changed`);
}
console.log('PASS: existing request contracts preserved across Health, Usage, Optimization, Cleanup, AI, Permissions, Automation and provider controls.');
