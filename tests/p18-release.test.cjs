'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {build,verify,dependencies}=require('../scripts/p18-release.cjs');
const temp=fs.mkdtempSync(path.join(os.tmpdir(),'bois-p18-release-'));
try{
 const out=path.join(temp,'release'),revision=process.env.GITHUB_SHA||'f'.repeat(40),m=build(path.resolve(__dirname,'..'),out,revision);
 assert(m.files.some(f=>f.path==='private/server/p17_mail.php'));
 assert(m.files.some(f=>f.path==='public/supporter-preview.html'));
 const mail=path.join(out,'private/server/p17_mail.php'),content=fs.readFileSync(mail);
 fs.unlinkSync(mail);assert.throws(()=>dependencies(out),/dependency/);assert.throws(()=>verify(out),/file-set/);
 fs.writeFileSync(mail,Buffer.concat([content,Buffer.from('\n// altered')]));assert.throws(()=>verify(out),/hash/);
 fs.writeFileSync(mail,content);verify(out);
 const link=path.join(out,'public/leak');fs.symlinkSync(mail,link);assert.throws(()=>verify(out),/Symlink/);fs.unlinkSync(link);
 fs.writeFileSync(path.join(out,'public/extra.php'),'<?php');assert.throws(()=>verify(out),/file-set/);
 console.log('P18_RELEASE_COMPLETE_TAMPER_MISSING_DEPENDENCY_SYMLINK_EXTRA_FILE: pass');
}finally{fs.rmSync(temp,{recursive:true,force:true});}
