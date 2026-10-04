'use strict';
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const runtimeFiles=['security.php','http-errors.php','production.php','p14_admin.php','p15_observability.php','p17_mail.php','p17-worker.php','commerce-api.php','consent.php','p3_db.php','p3-migrate.php','p4_membership.php','p4-migrate.php','p5_batch.php','p5-migrate.php','p5-worker.php','p6_payment.php','p6-migrate.php','p7_assortment.php','p7-migrate.php','p8_stripe.php','p8-migrate.php','p9_sales.php','p9-migrate.php','stripe-webhook.php'];
function files(root){const result=[];for(const name of fs.readdirSync(root).sort()){const p=path.join(root,name),s=fs.lstatSync(p);if(s.isSymbolicLink())throw Error('Symlink refused');if(s.isDirectory())result.push(...files(p));else if(s.isFile())result.push(p);else throw Error('Unsupported file');}return result;}
function dependencies(root){
  root=path.resolve(root);
  for(const p of files(root).filter(p=>p.endsWith('.php'))){
    const source=fs.readFileSync(p,'utf8');
    const re=/\b(?:require_once|require|include_once|include)\s*(?:\(\s*)?(__DIR__|dirname\(__DIR__\))\s*\.\s*(['"])([^'"]+)\2/g;
    for(const m of source.matchAll(re)){
      const base=m[1]==='__DIR__'?path.dirname(p):path.dirname(path.dirname(p));
      const target=path.resolve(base,'.'+m[3]);
      if(!target.startsWith(root+path.sep)||!fs.existsSync(target)||!fs.lstatSync(target).isFile())throw Error('Missing/private-path dependency: '+path.relative(root,p)+' -> '+m[3]);
    }
  }
}
const hash=p=>crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
function build(source,destination,revision){
  if(!/^[a-f0-9]{40}$/.test(revision))throw Error('Exact revision required');
  source=path.resolve(source);destination=path.resolve(destination);
  if(destination===source||destination.startsWith(source+path.sep))throw Error('Build outside source tree');
  if(fs.existsSync(destination)&&fs.readdirSync(destination).length)throw Error('Empty build target required');
  fs.mkdirSync(path.join(destination,'private/server'),{recursive:true});fs.mkdirSync(path.join(destination,'private/ops'),{recursive:true});
  fs.mkdirSync(path.join(destination,'templates'),{recursive:true});
  for(const f of runtimeFiles){const p=path.join(source,'server',f);if(!fs.lstatSync(p).isFile())throw Error('Regular runtime source required');fs.copyFileSync(p,path.join(destination,'private/server',f));}
  fs.cpSync(path.join(source,'data'),path.join(destination,'private/data'),{recursive:true,filter:p=>{if(fs.lstatSync(p).isSymbolicLink())throw Error('Symlink source refused');return true;}});
  for(const p of files(path.join(source,'ops')))fs.copyFileSync(p,path.join(destination,'private/ops',path.basename(p)));
  fs.cpSync(path.join(source,'site/commerce'),path.join(destination,'public'),{recursive:true,filter:p=>{if(fs.lstatSync(p).isSymbolicLink())throw Error('Symlink source refused');return true;}});
  fs.copyFileSync(path.join(source,'server/production-config.example.php'),path.join(destination,'templates/production-config.example.php'));
  fs.writeFileSync(path.join(destination,'public/config.js'),"window.BOIS_COMMERCE_CONFIG=Object.freeze({apiBase:'./commerce-api.php',environmentLabel:'STÄNGD RELEASE',paymentEnabled:false,adminAuth:'personal'});\n");
  fs.writeFileSync(path.join(destination,'public/commerce-api.php'),"<?php require dirname(__DIR__).'/private/server/commerce-api.php';\n");
  fs.writeFileSync(path.join(destination,'public/.htaccess'),"Options -Indexes\n<FilesMatch \\.(?:json|log|sql|tar|gz|zip|ini)$>\nRequire all denied\n</FilesMatch>\n");
  dependencies(destination);
  const manifest={format:1,source_revision:revision,activation:'closed',files:files(destination).map(p=>({path:path.relative(destination,p).split(path.sep).join('/'),sha256:hash(p)}))};
  fs.writeFileSync(path.join(destination,'manifest.json'),JSON.stringify(manifest,null,2)+'\n');
  verify(destination);return manifest;
}
function verify(root){
  root=path.resolve(root);const m=JSON.parse(fs.readFileSync(path.join(root,'manifest.json'),'utf8'));
  if(m.format!==1||m.activation!=='closed'||!/^[a-f0-9]{40}$/.test(m.source_revision))throw Error('Invalid release manifest');
  const expected=m.files.map(x=>x.path).sort(),actual=files(root).map(p=>path.relative(root,p).split(path.sep).join('/')).filter(p=>p!=='manifest.json').sort();
  if(JSON.stringify(expected)!==JSON.stringify(actual))throw Error('Manifest file-set mismatch');
  for(const f of m.files){if(!/^[a-f0-9]{64}$/.test(f.sha256)||hash(path.join(root,f.path))!==f.sha256)throw Error('Release hash mismatch');}
  dependencies(root);return m;
}
module.exports={build,verify,dependencies,runtimeFiles};
if(require.main===module){try{
  const [action,...args]=process.argv.slice(2);
  if(action==='build'){const m=build(...args);console.log('RELEASE_CLOSED_FILES: '+m.files.length+'\nSOURCE_REVISION: '+m.source_revision);}
  else if(action==='verify') {verify(args[0]);console.log('RELEASE_MANIFEST_AND_DEPENDENCIES: pass');}
  else if(action==='verify-dependencies'){dependencies(args[0]);console.log('PAYLOAD_STATIC_PHP_DEPENDENCIES: pass');}
  else throw Error('Unknown release action');
}catch(e){console.error(e.message);process.exitCode=1;}}
