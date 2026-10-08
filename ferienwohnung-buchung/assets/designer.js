(() => {
'use strict';
const cfg=window.FWBDesigner;if(!cfg)return;
const el=(tag,cls='',text='')=>{const n=document.createElement(tag);n.className=cls;n.textContent=text;return n;};
const button=(text,fn,cls='button')=>{const b=el('button',cls,text);b.type='button';b.addEventListener('click',fn);return b;};
const api=async(op,payload={})=>{
  const body=new FormData();body.set('action','fwb_designer');body.set('nonce',cfg.nonce);body.set('op',op);body.set('fwb_lang',cfg.language);body.set('payload',JSON.stringify(payload));
  const r=await fetch(cfg.ajax,{method:'POST',body});const data=await r.json();if(!data.success)throw Error(data.data?.message||'Anfrage fehlgeschlagen.');return data.data;
};
const field=(parent,key,def,object,changed)=>{
 const label=el('label','fwb-setting',def.label), input=el(def.type==='select'?'select':'input');
 if(def.type==='select')Object.entries(def.options).forEach(([v,t])=>{const o=el('option','',t);o.value=v;input.append(o);});
 else input.type=def.type==='color'?'text':def.type;
 input.setAttribute('aria-label',def.label);
 if(def.type==='number'){input.min=def.min;input.max=def.max;input.step='any';}
 if(def.type==='checkbox')input.checked=!!object[key];else input.value=object[key]??'';
 input.addEventListener(def.type==='select'||def.type==='checkbox'?'change':'input',()=>{object[key]=def.type==='checkbox'?input.checked:input.value;changed();});
 if(def.type==='color'){
   input.placeholder='Theme / #RRGGBB';input.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(input.value))pick.value=input.value;});input.pattern='#[0-9a-fA-F]{6}|';const pick=el('input');pick.type='color';pick.value=/^#[0-9a-f]{6}$/i.test(input.value)?input.value:'#ffffff';pick.setAttribute('aria-label',def.label+' auswählen');
   pick.addEventListener('input',()=>{input.value=pick.value;object[key]=pick.value;changed();});
   const row=el('div','fwb-color');row.append(pick,input,button('↺',()=>{input.value='';object[key]='';changed();}));row.lastElementChild.title='Zurücksetzen: '+def.label;row.lastElementChild.setAttribute('aria-label','Zurücksetzen: '+def.label);label.append(row);
 }else label.append(input);
 parent.append(label);return input;
};
const download=(name,data)=>{const url=URL.createObjectURL(new Blob([JSON.stringify(data,null,2)],{type:'application/json'}));const a=el('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);};
const draftKey=kind=>'fwb-'+kind+'-'+cfg.site+'-'+cfg.user+'-'+cfg.language;
const storeDraft=(kind,data)=>{try{localStorage.setItem(draftKey(kind),JSON.stringify(data));}catch(_){}};
const getDraft=kind=>{try{return JSON.parse(localStorage.getItem(draftKey(kind))||'null');}catch(_){return null;}};
const clearDraft=kind=>{try{localStorage.removeItem(draftKey(kind));}catch(_){}};
window.FWBUi={el,button,field,api,cfg,download,storeDraft,getDraft,clearDraft};
document.querySelector('#fwb-language-save')?.addEventListener('click',async()=>{const p={};document.querySelectorAll('#fwb-language-settings [data-key]').forEach(e=>p[e.dataset.key]=e.value);const status=document.querySelector('#fwb-language-status');try{status.textContent=(await api('language_settings',p)).message;}catch(e){status.textContent=e.message;}});
const global=document.querySelector('#fwb-global-design');
if(global){
 let data={...cfg.design},dirty=false;const form=el('form');const bar=el('div','fwb-studio-toolbar');const status=document.querySelector('#fwb-design-status');
 const report=async fn=>{try{const r=await fn();status.textContent=r.message||'Fertig.';}catch(e){status.textContent=e.message;}};
 const fields=el('div');
 const draw=()=>{fields.replaceChildren();
 const groups={
 'Kalender · verfügbare Tage':['free','day_text','today','calendar_hover','calendar_border'],
 'Kalender · Belegung & Auswahl':['busy','busy_text','selection','selection_text','selection_border'],
 'Kalender · vergangene & nicht auswählbare Tage':['past_background','past_text','disabled_background','disabled_text'],
 'Kalender · Größen & Rundung':['calendar_radius','calendar_font_size','calendar_height'],
 'Formularfelder & Meldungen':['input_background','input_color','input_border','label_color','help_color','field_radius','field_border_width','error','success','focus'],
 'Buttons':['button_background','button_color','button_hover','button_hover_color'],
 'Abstände & Rundung':['column_gap','element_gap','radius']
 };
 Object.entries(groups).forEach(([name,keys])=>{const section=el('fieldset','fwb-design-section'),grid=el('div','fwb-design-grid');section.append(el('legend','',name),grid);keys.forEach(k=>field(grid,k,cfg.globalSchema[k],data,()=>{dirty=true;status.textContent='Ungespeicherte Änderungen';storeDraft('design',data);}));fields.append(section);});
 };

 bar.append(button('Design speichern',()=>{if(form.reportValidity())report(async()=>{const r=await api('design_save',data);clearDraft('design');dirty=false;return r;});},'button button-primary'),
 button('Vorgaben zurücksetzen',()=>{data={...cfg.defaults};dirty=true;storeDraft('design',data);draw();status.textContent='Vorgaben geladen – zum Übernehmen speichern.';}),
 button('Exportieren',()=>report(async()=>{download('ferienwohnung-design.json',await api('design_export'));return {message:'Gespeicherte Gestaltung exportiert.'};})));
 const file=el('input');file.type='file';file.accept='.json,application/json';file.hidden=true;
 file.addEventListener('change',()=>report(async()=>{if(!file.files[0])return {};if(file.files[0].size>1000000)throw Error('Datei zu groß.');const p=JSON.parse(await file.files[0].text());const r=await api('design_import',p);data=p.design;dirty=false;clearDraft('design');draw();return r;}));
 bar.append(button('Importieren',()=>file.click()),button('Vor Import wiederherstellen',()=>report(async()=>{const r=await api('design_restore');const p=await api('design_export');data=p.design;dirty=false;clearDraft('design');draw();return r;})),file);
 const draft=getDraft('design');if(draft)bar.append(button('Lokalen Entwurf laden',()=>{data=draft;dirty=true;draw();status.textContent='Entwurf geladen, noch nicht gespeichert.';}));
 form.addEventListener('submit',e=>e.preventDefault());form.append(bar,fields);global.append(form);draw();window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
}
const host=document.querySelector('#fwb-template-designer');if(!host)return;
let templates=JSON.parse(host.dataset.templates),pdf={...cfg.pdfDesign},kind='request',active='body',dirty=false,bookmark=null,range=[0,0],blob=null;
const kinds={request:'Anfrage eingegangen',confirmed:'Buchungsbestätigung',rejected:'Ablehnung',cancelled:'Stornierung',invoice:'Rechnungsversand',signature:'Mail-Signatur',pdf_header:'PDF · Kopfzeile',pdf_body:'PDF · Inhalt',pdf_footer:'PDF · Fußzeile'};
const list=document.querySelector('#fwb-template-list'),options=document.querySelector('#fwb-template-options'),toolbar=document.querySelector('#fwb-template-toolbar');
const subject=document.querySelector('#fwb-template-subject'),area=document.querySelector('#fwb_template_content'),status=document.querySelector('#fwb-template-status');
const preview=document.querySelector('#fwb-template-preview'),frame=preview.querySelector('iframe');
const editor=()=>window.tinymce?.get('fwb_template_content');
const bodyKey=()=>kind.startsWith('pdf_')||kind==='signature'?kind:kind+'_body';
const snapshot=()=>JSON.stringify({templates,pdf});let history=[snapshot()],pos=0;
const flush=()=>{const ed=editor();templates[bodyKey()]=ed&&!ed.isHidden()?ed.getContent():area.value;if(!kind.startsWith('pdf_')&&kind!=='signature')templates[kind+'_subject']=subject.value;};
const changed=()=>{dirty=true;flush();const value=snapshot();if(history[pos]!==value){history=history.slice(0,pos+1);history.push(value);if(history.length>50)history.shift();pos=history.length-1;}storeDraft('templates',{templates,pdf});status.textContent='Ungespeicherte Änderungen';};
const draw=()=>{
 document.querySelector('#fwb-template-title').textContent=kinds[kind];document.querySelector('#fwb-subject-label').hidden=kind.startsWith('pdf_')||kind==='signature';document.querySelector('#fwb-signature-help').hidden=kind!=='signature';
 subject.value=templates[kind+'_subject']||'';area.value=templates[bodyKey()]||'';if(editor())editor().setContent(area.value);
 list.querySelectorAll('button').forEach(b=>b.classList.toggle('is-active',b.dataset.kind===kind));preview.hidden=true;active='body';bookmark=null;drawOptions();
};
Object.entries(kinds).forEach(([key,title])=>{const b=button(title,()=>{flush();kind=key;draw();});b.dataset.kind=key;list.append(b);});
const insert=token=>{
 if(active==='subject'){subject.focus();subject.setRangeText(token,range[0],range[1],'end');range=[subject.selectionStart,subject.selectionEnd];}
 else {const ed=editor();if(ed&&!ed.isHidden()){ed.focus();if(bookmark)ed.selection.moveToBookmark(bookmark);ed.insertContent(token);bookmark=ed.selection.getBookmark(2,true);}else{area.focus();area.setRangeText(token,area.selectionStart,area.selectionEnd,'end');}}
 changed();
};
function drawOptions(){
 options.replaceChildren();const h=el('h3','','Platzhalter');const search=el('input');search.type='search';search.placeholder='Platzhalter suchen';search.setAttribute('aria-label','Platzhalter suchen');options.append(h,search);
 const tokens=el('div','fwb-tokens');Object.entries(JSON.parse(host.dataset.tokens)).forEach(([group,keys])=>{
   const box=el('section');box.append(el('h4','',group));
   keys.forEach(key=>{if(key==='items'&&kind!=='pdf_body')return;const b=button('{'+key+'}',()=>insert('{'+key+'}'));b.dataset.token=key;
     b.addEventListener('mousedown',e=>e.preventDefault());box.append(b);});tokens.append(box);
 });search.addEventListener('input',()=>tokens.querySelectorAll('[data-token]').forEach(b=>b.hidden=!b.dataset.token.includes(search.value.toLowerCase())));options.append(tokens);
 if(kind.startsWith('pdf_')&&!cfg.translation){
   const details=el('details','fwb-pdf-controls');details.open=true;details.append(el('summary','','PDF-Gestaltung'));
   Object.entries(cfg.pdfSchema).forEach(([key,def])=>{
     if(key==='logo_id'){
       const p=el('p');p.append(button('Logo aus Mediathek',()=>{const media=wp.media({title:'Rechnungslogo wählen',multiple:false,library:{type:'image'}});media.on('select',()=>{pdf.logo_id=media.state().get('selection').first().toJSON().id;changed();drawOptions();});media.open();}),
       button('Logo entfernen',()=>{pdf.logo_id='0';changed();drawOptions();}),el('span','',Number(pdf.logo_id)?' Logo #'+pdf.logo_id:' Kein Logo'));details.append(p);
     }else field(details,key,def,pdf,changed);
   });options.append(details);
 }
}
const rememberSubject=()=>{active='subject';range=[subject.selectionStart,subject.selectionEnd];};
subject.addEventListener('focus',rememberSubject);subject.addEventListener('keyup',rememberSubject);subject.addEventListener('mouseup',rememberSubject);subject.addEventListener('input',changed);
area.addEventListener('focus',()=>active='body');area.addEventListener('input',changed);
const setup=ed=>{if(ed.id!=='fwb_template_content'||ed.fwbDesignerBound)return;ed.fwbDesignerBound=true;if(ed.initialized)ed.setContent(templates[bodyKey()]||'');ed.on('init',()=>ed.setContent(templates[bodyKey()]||''));ed.on('focus keyup mouseup',()=>{active='body';bookmark=ed.selection.getBookmark(2,true);});ed.on('change input undo redo',changed);};
if(window.jQuery)jQuery(document).on('tinymce-editor-init.fwbTemplates',(_,ed)=>setup(ed));
if(window.tinymce){tinymce.on('AddEditor',e=>setup(e.editor));if(editor())setup(editor());}
const payload=()=>{flush();return {templates,pdf,kind,booking:booking.value};};
const run=async fn=>{try{status.textContent='Wird verarbeitet …';await fn();}catch(e){status.textContent=e.message;}};
const booking=el('select');booking.setAttribute('aria-label','Vorschaudaten');Object.entries(JSON.parse(host.dataset.bookings)).forEach(([v,t])=>{const o=el('option','',t);o.value=v;booking.append(o);});
toolbar.append(button('Vorlagen speichern',()=>run(async()=>{const r=await api('templates_save',payload());dirty=false;clearDraft('templates');status.textContent=r.message;}),'button button-primary'),
 button('↶ Rückgängig',()=>{flush();if(pos>0){({templates,pdf}=JSON.parse(history[--pos]));draw();dirty=true;storeDraft('templates',{templates,pdf});}}),
 button('↷ Wiederholen',()=>{if(pos<history.length-1){({templates,pdf}=JSON.parse(history[++pos]));draw();dirty=true;storeDraft('templates',{templates,pdf});}}),booking,
 button('Vorschau aktualisieren',()=>run(async()=>{const r=await api('template_preview',payload());preview.hidden=false;if(blob)URL.revokeObjectURL(blob);
 if(r.pdf){document.querySelector('#fwb-preview-subject').textContent='PDF-Vorschau · Nicht ausgestellt';frame.removeAttribute('srcdoc');blob=URL.createObjectURL(new Blob([Uint8Array.from(atob(r.pdf),c=>c.charCodeAt(0))],{type:'application/pdf'}));frame.removeAttribute('sandbox');frame.src=blob;}
 else{frame.setAttribute('sandbox','');frame.removeAttribute('src');frame.srcdoc=r.html;document.querySelector('#fwb-preview-subject').textContent=r.subject;}status.textContent='Vorschau aktualisiert – nicht gespeichert.';})));
const recipient=el('input');recipient.type='email';recipient.placeholder='Test-E-Mail-Adresse';recipient.setAttribute('aria-label','Test-E-Mail-Adresse');
toolbar.append(recipient,button('Testmail senden',()=>run(async()=>{if(kind.startsWith('pdf_'))throw Error('Bitte eine E-Mail-Vorlage wählen.');if(!recipient.value||!recipient.reportValidity())throw Error('Bitte Testadresse eingeben.');const r=await api('test_mail',{...payload(),recipient:recipient.value});status.textContent=r.message;})));
const devices=el('select');devices.setAttribute('aria-label','Vorschau-Breite');Object.entries({'100%':'Desktop','768px':'Tablet','390px':'Smartphone'}).forEach(([v,t])=>{const o=el('option','',t);o.value=v;devices.append(o);});devices.addEventListener('change',()=>{frame.style.width=devices.value;});toolbar.append(devices);
const draft=getDraft('templates');if(draft)toolbar.append(button('Lokalen Entwurf laden',()=>{({templates,pdf}=draft);draw();changed();}));
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});draw();
})();
