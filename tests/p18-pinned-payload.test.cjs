'use strict';
const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {dependencies}=require('../scripts/p18-release.cjs');
const [workflow,source]=process.argv.slice(2),text=fs.readFileSync(workflow,'utf8');
const out=fs.mkdtempSync(path.join(os.tmpdir(),'bois-pinned-payload-'));
try{
  let copied=0;
  // Use the actual workflow's server-copy loops, including their optional guard.
  for(const m of text.matchAll(/for f in ([^;\n]+); do([\s\S]*?)done/g)){
    if(!m[2].includes('bois-src/server/$f'))continue;
    const optional=m[2].includes('[[ -f');
    for(const name of m[1].trim().split(/\s+/)){
      const input=path.join(source,'server',name);
      if(!fs.existsSync(input)){if(optional)continue;throw Error('Pinned mandatory module absent: '+name);}
      fs.copyFileSync(input,path.join(out,name));copied++;
    }
  }
  if(copied===0||!fs.existsSync(path.join(out,'commerce-api.php')))throw Error('Workflow root missing');
  dependencies(out);console.log('P18_ACTUAL_PINNED_WORKFLOW_PAYLOAD: pass '+path.basename(workflow));
}finally{fs.rmSync(out,{recursive:true,force:true});}
