(() => {
  'use strict';
  document.querySelectorAll('[data-confirm]').forEach(b=>b.addEventListener('click',e=>{
    e.preventDefault();
    if(!b.form.reportValidity())return;
    const bulk=b.form.id==='fwb-bulk';
    const selected=bulk?document.querySelectorAll('.fwb-booking-select:checked').length:1;
    if(!selected){document.querySelector('#fwb-selection-count').textContent='Bitte Buchungen auswählen.';return;}
    const action=b.value||b.form.elements.bulk_action?.value;
    const silent=['trash','restore','unblock'].includes(action);
    const dialog=document.createElement('dialog');dialog.className='fwb-action-dialog';dialog.setAttribute('aria-labelledby','fwb-action-title');
    const heading=document.createElement('h2');heading.id='fwb-action-title';heading.textContent='Aktion bestätigen';
    const text=document.createElement('p');
    const bulkHelp={trash:'Belegte Zeiträume werden freigegeben. Keine Mail; Rechnungen bleiben erhalten.',restore:'Ursprünglichen Status wiederherstellen. Belegte Zeiträume werden erneut geprüft. Keine Mail.',unblock:'Nur Sperrzeiten werden freigegeben. Keine Mail.'};
    text.textContent=bulk?selected+' ausgewählte Buchungen: '+b.form.elements.bulk_action.selectedOptions[0].text+'. '+(bulkHelp[action]||'Jede Buchung wird einzeln geprüft. Nicht passende Einträge werden übersprungen.'):b.dataset.confirm;
    const mail=b.form?.elements.send_mail;
    if(mail&&!silent){text.textContent+=' '+(mail.checked?'Mailversand ist eingeschaltet.':'Es wird keine Mail versendet.');}
    const cancel=document.createElement('button');cancel.type='button';cancel.className='button';cancel.textContent='Abbrechen';
    const confirm=document.createElement('button');confirm.type='button';confirm.className='button button-primary';confirm.textContent='Jetzt ausführen';
    cancel.addEventListener('click',()=>dialog.close());
    confirm.addEventListener('click',()=>{dialog.close();b.form.requestSubmit(b);});
    dialog.addEventListener('close',()=>{dialog.remove();b.focus();});
    dialog.append(heading,text,cancel,confirm);document.body.append(dialog);dialog.showModal();cancel.focus();
  }));
  const selectAll=document.querySelector('#fwb-select-all');
  if(selectAll){
    const checks=[...document.querySelectorAll('.fwb-booking-select')];
    const update=()=>{const count=checks.filter(c=>c.checked).length;selectAll.checked=checks.length>0&&count===checks.length;selectAll.indeterminate=count>0&&count<checks.length;document.querySelector('#fwb-selection-count').textContent=count+' ausgewählt';};
    selectAll.addEventListener('change',()=>{checks.forEach(c=>c.checked=selectAll.checked);update();});checks.forEach(c=>c.addEventListener('change',update));
    document.querySelector('#fwb-bulk').addEventListener('submit',e=>{if(!checks.some(c=>c.checked)){e.preventDefault();document.querySelector('#fwb-selection-count').textContent='Bitte Buchungen auswählen.';}});
    const bulkForm=document.querySelector('#fwb-bulk');
    const updateMail=()=>{bulkForm.elements.send_mail.disabled=['trash','restore','unblock'].includes(bulkForm.elements.bulk_action.value);};
    bulkForm.elements.bulk_action.addEventListener('change',updateMail);updateMail();
    update();
  }
  const manual=document.querySelector('#fwb-manual');
  if(manual){
    const form=manual.querySelector('form'),result=manual.querySelector('#fwb-manual-result');
    const preview=manual.querySelector('#fwb-manual-quote');let revision=0;
    const workspace=manual.closest('.fwb-calendar-workspace');
    if(workspace){
      const blockForm=workspace.querySelector('.fwb-calendar-column form');
      const blockButton=blockForm.querySelector('button');
      const syncRange=()=>{
        const arrival=form.elements.arrival.value,departure=form.elements.departure.value;
        blockForm.elements.arrival.value=arrival;
        blockForm.elements.departure.value=departure;
        blockButton.disabled=!arrival||departure<=arrival;
      };
      form.addEventListener('change',()=>{syncRange();revision++;result.textContent='Zeitraum oder Daten geändert – Preis und Verfügbarkeit erneut prüfen.';});
      form.addEventListener('input',syncRange);
      blockForm.addEventListener('submit',e=>{
        syncRange();
        if(blockButton.disabled){e.preventDefault();result.textContent='Bitte zuerst Anreise und Abreise im Kalender auswählen.';}
      });
      syncRange();
    }
    form.addEventListener('input',()=>{revision++;result.textContent='Daten geändert – Preis und Verfügbarkeit erneut prüfen.';});
    preview.addEventListener('click',async()=>{
      const current=++revision;preview.disabled=true;result.textContent='Wird geprüft …';
      try{const p=Object.fromEntries(new FormData(form));const r=await window.FWBUi.api('manual_quote',p);if(current===revision)result.textContent=r.message;}
      catch(e){if(current===revision)result.textContent=e.message;}finally{preview.disabled=false;}
    });
  }
  const host = document.querySelector('#fwb-builder'); if (!host) return;
  let layout = JSON.parse(host.dataset.layout); const catalog = JSON.parse(host.dataset.catalog), ui=window.FWBUi;
  allDefaults();
  function allDefaults() {
    Object.values(layout.zones).flat().forEach(b=>{
      if(b.type==='quote'){
        b.summary_template ??= '{total} für {nights} Nächte';
        b.detail_template ??= 'Anzahlung {deposit} · Restbetrag {balance}. Ortstaxe separat vor Ort: {local_tax}.';
      }
    });
  }
  const zones = { top: 'Über beiden Spalten', left: 'Linke Spalte', right: 'Rechte Spalte', bottom: 'Unter beiden Spalten' };
  const required = ['arrival','departure','guests','taxable_guests','quote','consent','submit'];
  const inspector = document.querySelector('#fwb-inspector'), properties = document.querySelector('#fwb-inspector-fields');
  const richPanel = document.querySelector('#fwb-rich-panel'), richArea = document.querySelector('#fwb_rich_content');
  let selected = null, destination = 'right', dirty = false, activeTab='content', previewMode=false;
  let history=[JSON.stringify(layout)], historyPos=0, previewVersion=0, saving=false;
  const all = () => Object.values(layout.zones).flat();
  const current = () => all().find(b => b.id === selected);
  const locate = id => {
    for (const [zone, blocks] of Object.entries(layout.zones)) {
      const index = blocks.findIndex(b => b.id === id);
      if (index >= 0) return {zone, index, block: blocks[index]};
    }
  };
  const mandatory = b => required.includes(b.type) || (b.type === 'field' && (['name','email','address'].includes(b.field.key)||b.field.required));
  const isRich = b => b && ['conditions','text'].includes(b.type);
  const editor = () => window.tinymce?.get('fwb_rich_content');
  const sync = () => { document.querySelector('#fwb-layout-json').value = JSON.stringify(layout); };
  const status = text => { document.querySelector('#fwb-builder-status').textContent = text;document.querySelector('#fwb-modal-status').textContent=text; };
  const flush = () => {
    const b = current();
    if (isRich(b)) {
      const ed = editor(); b.content = ed && !ed.isHidden() ? ed.getContent() : richArea.value;
    }
    sync();
  };
  const change = () => {
    dirty = true; sync(); const serialized=JSON.stringify(layout);
    if(history[historyPos]!==serialized){history=history.slice(0,historyPos+1);history.push(serialized);if(history.length>50)history.shift();historyPos=history.length-1;}
    ui.storeDraft('layout',layout); status('Ungespeicherte Änderungen');
  };
  const button = (text, fn, title=text) => {
    const b = document.createElement('button'); b.type='button'; b.className='button'; b.textContent=text; b.setAttribute('aria-label',title); b.addEventListener('click',fn); return b;
  };
  const control = (label, value, update, options=null, multiline=false) => {
    const l=document.createElement('label'); l.append(document.createTextNode(label));
    const el=document.createElement(options?'select':multiline?'textarea':'input');
    if (options) Object.entries(options).forEach(([v,t])=>{const o=document.createElement('option');o.value=v;o.textContent=t;el.append(o);});
    if (multiline) el.rows=3;
    el.value=value??''; el.addEventListener(options?'change':'input',()=>{update(el.value);change();});
    l.append(el);properties.append(l);return el;
  };
  const inspect = () => {
    const b=current(); inspector.hidden=!b;document.body.classList.toggle('fwb-modal-open',!!b); if(!b) return;
    properties.replaceChildren(); document.querySelector('#fwb-inspector-title').textContent='Bearbeiten: '+(b.label||catalog[b.type]||'Feld');
    const tabs=document.querySelector('#fwb-inspector-tabs');tabs.replaceChildren();
    Object.entries({content:'Inhalt',style:'Stil',advanced:'Erweitert'}).forEach(([key,title])=>{
      const btn=button(title,()=>{flush();activeTab=key;inspect();});btn.disabled=ui.cfg.translation&&key!=='content';btn.setAttribute('aria-pressed',String(activeTab===key));tabs.append(btn);
    });
    if(activeTab==='content') {
    control(b.type==='submit'?'Buttontext':'Beschriftung / Überschrift',b.label,v=>{b.label=v;if(b.field)b.field.label=v; const title=host.querySelector('[data-card="'+b.id+'"] .fwb-card-name');if(title)title.textContent=v||catalog[b.type]||'Feld';});
    if(!ui.cfg.translation)control('Breite im Bereich',b.width,v=>{b.width=v;renderCards();},{full:'Ganze Breite',half:'Halbe Breite',third:'Drittel',two_thirds:'Zwei Drittel'});
    if(!ui.cfg.translation&&b.type==='heading') control('Überschriftengröße',b.level||'h3',v=>b.level=v,{h2:'Groß (H2)',h3:'Mittel (H3)',h4:'Klein (H4)'});
    if(b.type==='calendar') {
      control('Hinweis unter dem Kalender',b.content,v=>b.content=v,null,true);
      b.calendar_labels ||= {free_label:'Frei',busy_label:'Belegt',half_label:'An-/Abreise',selected_label:'Ausgewählt'};
      Object.entries({free_label:'Legende: frei',busy_label:'Legende: belegt',half_label:'Legende: halber Tag',selected_label:'Legende: Auswahl'}).forEach(([k,l])=>control(l,b.calendar_labels[k],v=>b.calendar_labels[k]=v));
    }
    if(b.type==='quote') {
      control('Hinweis ohne Reisedaten',b.content,v=>b.content=v,null,true);
      b.summary_template ??= '{total} für {nights} Nächte';
      b.detail_template ??= 'Anzahlung {deposit} · Restbetrag {balance}. Ortstaxe separat vor Ort: {local_tax}.';
      control('Preissumme – mit {total} und optional {nights}',b.summary_template,v=>b.summary_template=v);
      control('Preisdetails – mit {deposit}, {balance}, {local_tax}',b.detail_template,v=>b.detail_template=v,null,true);
    }
    if(b.type==='consent') { const p=document.createElement('p');p.textContent='Der Platzhalter {privacy_link} bleibt erforderlich und verlinkt deine Datenschutzhinweise.';properties.append(p); }
    if(b.type==='field') {
      const f=b.field, core=['name','email','address'].includes(f.key);
      const key=control('Kennung / Mail-Platzhalter',f.key,v=>f.key=v);key.disabled=core||ui.cfg.translation;
      const type=control('Feldtyp',f.type,v=>{flush();f.type=v;inspect();},{text:'Text',email:'E-Mail',tel:'Telefon',textarea:'Mehrzeiliger Text',select:'Auswahl',checkbox:'Kontrollkästchen'});type.disabled=core||ui.cfg.translation;
      const req=control('Pflichtfeld',f.required?'yes':'no',v=>f.required=v==='yes',{yes:'Ja',no:'Nein'});req.disabled=core||ui.cfg.translation;
      if(f.type==='select') control('Auswahloptionen – eine pro Zeile',Array.isArray(f.options)?f.options.join('\n'):f.options,v=>f.options=v,null,true);
    }
    } else if(activeTab==='style'){
      b.appearance ||= {};
      const textKeys=['text_style','text_color','align','font_size','line_height','color','margin','padding','background','border_color','border_width','radius','shadow','width_s','width_m','width_l'];
      if(['text','conditions'].includes(b.type))textKeys.push('dropcap','columns','column_breakpoint','column_divider');
      if(['heading','conditions','calendar','quote'].includes(b.type))textKeys.push('heading_size');
      if(['field','arrival','departure','guests','taxable_guests'].includes(b.type))textKeys.push('input_size','input_background','input_color','input_border','placeholder','help','autocomplete','inputmode');
      if(b.type==='field'&&['text','email','tel','textarea'].includes(b.field.type))textKeys.push('maxlength');
      if(b.type==='field'&&b.field.type==='textarea')textKeys.push('rows');
      if(b.type==='submit')textKeys.push('button_style','button_size','button_width','button_background','button_color','button_hover','button_hover_color');
      const groups={
        'Schrift & Text':['text_style','text_color','color','align','font_size','line_height','heading_size'],
        'Textspalten & Initiale':['dropcap','columns','column_breakpoint','column_divider'],
        'Abstände & Breiten':['margin','padding','width_s','width_m','width_l'],
        'Hintergrund & Rahmen':['background','border_color','border_width','radius','shadow'],
        'Eingabefeld':['input_size','input_background','input_color','input_border','placeholder','help','autocomplete','inputmode','maxlength','rows'],
        'Button':['button_style','button_size','button_width','button_background','button_color','button_hover','button_hover_color']
      };
      Object.entries(groups).forEach(([title,keys])=>{
        const chosen=keys.filter(k=>textKeys.includes(k));if(!chosen.length)return;
        const group=ui.el('fieldset','fwb-style-group');group.append(ui.el('legend','',title));properties.append(group);
        chosen.forEach(key=>{
        const input=ui.field(group,key,ui.cfg.schema[key],b.appearance,()=>{
          change();
          if(key==='columns')properties.querySelectorAll('[data-columns-dependent]').forEach(e=>e.disabled=!b.appearance.columns);
        });
        if(['column_breakpoint','column_divider'].includes(key)){input.dataset.columnsDependent='1';input.disabled=!b.appearance.columns;}
      });
      });
      properties.append(button('Element-Stil zurücksetzen',()=>{b.appearance={};change();inspect();}));
    } else {
      b.advanced ||= {attributes:[],control_attributes:[]};
      control('HTML-ID (äußerer Container)',b.advanced.html_id,v=>b.advanced.html_id=v);
      control('CSS-Klassen (äußerer Container)',b.advanced.classes,v=>b.advanced.classes=v);
      b.appearance ||= {};ui.field(properties,'tag',ui.cfg.schema.tag,b.appearance,change);
      if(['field','arrival','departure','guests','taxable_guests','submit','consent'].includes(b.type))control('CSS-Klassen (Eingabe / Button)',b.advanced.control_classes,v=>b.advanced.control_classes=v);
      const rows=(key,title)=>{
        b.advanced[key] ||= [];const panel=ui.el('div','fwb-attribute-list');panel.append(ui.el('h4','',title));
        b.advanced[key].forEach((row,index)=>{
          const line=ui.el('div','fwb-attribute-row');
          ['name','value'].forEach(k=>{const input=ui.el('input');input.placeholder=k==='name'?'Attributname':'Wert';input.setAttribute('aria-label',title+' '+input.placeholder);input.value=row[k]||'';input.addEventListener('input',()=>{row[k]=input.value;change();});line.append(input);});
          line.append(button('×',()=>{b.advanced[key].splice(index,1);change();inspect();},'Attribut entfernen'));panel.append(line);
        });
        panel.append(button('+ Attribut',()=>{b.advanced[key].push({name:'',value:''});change();inspect();}));properties.append(panel);
      };
      rows('attributes','Container-Attribute');
      if(['field','arrival','departure','guests','taxable_guests','submit','consent'].includes(b.type))rows('control_attributes','Eingabe-Attribute');
      const help=ui.el('p','description','Erlaubt sind eigene data-Attribute, geeignete aria-Attribute, title, uk-tooltip und uk-icon. Interne Buchungsattribute bleiben geschützt.');properties.append(help);
    }
    if(ui.cfg.translation){
      b.appearance ||= {};for(const [k,title] of Object.entries({placeholder:'Eingabe-Platzhalter',help:'Hilfetext'}))if(k in b.appearance)control(title,b.appearance[k],v=>b.appearance[k]=v);
      for(const group of ['attributes','control_attributes'])for(const attr of b.advanced?.[group]||[])if(['title','aria-label','aria-description','uk-tooltip'].includes(attr.name))control(group+' · '+attr.name,attr.value,v=>attr.value=v);
    }
    richPanel.hidden=activeTab!=='content'||!isRich(b);
    if(isRich(b)) { richArea.value=b.content; if(editor()) editor().setContent(b.content); }
  };
  const select = id => {
    flush();selected=id;renderCards();inspect();
    document.querySelector('#fwb-inspector-title').focus({preventScroll:true});
  };
  const closeInspector=()=>{
    const id=selected;flush();selected=null;inspect();renderCards();
    if(id)host.querySelector('[data-card="'+id+'"] .fwb-edit')?.focus({preventScroll:true});
  };
  document.querySelector('#fwb-back-layout').addEventListener('click',closeInspector);
  document.querySelector('#fwb-modal-save').addEventListener('click',()=>host.closest('form').requestSubmit());
  document.addEventListener('keydown',e=>{
    if(inspector.hidden)return;
    // WordPress/TinyMCE popups have their own keyboard handling.
    if(e.target.closest('.mce-window,.mce-menu,.mce-floatpanel'))return;
    if(e.key==='Escape'){e.preventDefault();closeInspector();}
    if(e.key==='Tab'){
      const focusable=[...inspector.querySelectorAll('button,input,select,textarea,a[href],iframe,[tabindex="0"]')].filter(n=>!n.disabled&&n.getClientRects().length);
      const first=focusable[0],last=focusable.at(-1);
      if(e.shiftKey&&(document.activeElement===first||!inspector.contains(document.activeElement))){e.preventDefault();last?.focus();}
      else if(!e.shiftKey&&(document.activeElement===last||!inspector.contains(document.activeElement))){e.preventDefault();first?.focus();}
    }
  });
  const move = (id,zone,index) => {
    flush(); const old=locate(id);if(!old)return;
    if(old.zone===zone && old.index<index) index--;
    layout.zones[old.zone].splice(old.index,1);layout.zones[zone].splice(index,0,old.block);
    change();renderCards();status('Element nach '+zones[zone]+' verschoben.');
  };
  const create = type => {
    const id='block_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,7);
    const b={id,type,label:catalog[type],content:'',width:'full'};
    if(type.endsWith('_field')) {
      const ft=type.replace('_field','');b.type='field';b.field={key:'feld_'+id.slice(6),label:b.label,type:ft,required:false,options:[]};
    }
    if(['arrival','departure','guests','taxable_guests'].includes(type)) b.width='half';
    if(type==='heading') b.level='h3';
    if(type==='conditions') b.content=host.dataset.conditions;
    if(type==='text') { b.label='';b.content='<p>Dein Text</p>'; }
    if(type==='calendar') b.content='Halbe Markierung: Vormittag bzw. Nachmittag belegt. Ein Gästewechsel am selben Tag ist möglich.';
    if(type==='consent') b.label='Ich habe die Buchungsbedingungen und die {privacy_link} gelesen.';
    if(type==='quote') b.content='Wähle deine Reisedaten, um den Preis zu berechnen.';
    if(type==='submit') b.label='Unverbindlich anfragen';
    return b;
  };
  const singleton = type => !['heading','text'].includes(type) && !type.endsWith('_field');
  const add = (type,zone,index=layout.zones[zone].length) => {
    flush();if(singleton(type)&&all().some(b=>b.type===type))return;
    const b=create(type);layout.zones[zone].splice(index,0,b);selected=b.id;change();renderCards();inspect();document.querySelector('#fwb-inspector-title').focus();status(catalog[type]+' hinzugefügt.');
  };
  const shell=document.createElement('div');shell.className='fwb-builder-shell';
  const palette=document.createElement('aside');palette.className='fwb-palette';
  const title=document.createElement('h3');title.textContent='Verfügbare Elemente';palette.append(title);
  const destLabel=document.createElement('label');destLabel.textContent='Ziel bei Klick';
  const dest=document.createElement('select');Object.entries(zones).forEach(([k,v])=>{const o=document.createElement('option');o.value=k;o.textContent=v;dest.append(o);});dest.value=destination;dest.addEventListener('change',()=>destination=dest.value);destLabel.append(dest);palette.append(destLabel);
  Object.entries(catalog).forEach(([type,label])=>{
    const b=button('+ '+label,()=>add(type,destination),'Hinzufügen: '+label);b.draggable=false;b.dataset.palette=type;
    palette.append(b);
  });
  const canvas=document.createElement('div');canvas.className='fwb-canvas';
  const lists={};
  Object.entries(zones).forEach(([zone,label])=>{
    const area=document.createElement('section');area.className='fwb-zone fwb-zone-'+zone;
    const h=document.createElement('h3');h.textContent=label;
    const list=document.createElement('div');list.className='fwb-dropzone';list.dataset.zone=zone;list.setAttribute('aria-label',label);
    area.append(h,list);canvas.append(area);lists[zone]=list;
  });
  const renderCards = () => {
    Object.entries(lists).forEach(([zone,list])=>{
      list.replaceChildren();
      if(!layout.zones[zone].length){const hint=document.createElement('p');hint.className='description';hint.textContent='Element hierher ziehen';list.append(hint);}
      layout.zones[zone].forEach((b,index)=>{
        const row=document.createElement('article');row.className='fwb-layout-card'+(b.width==='half'?' fwb-half':'')+(selected===b.id?' is-selected':'');row.dataset.card=b.id;
        const handle=button('⠿',()=>{},'Verschieben: '+(b.label||catalog[b.type]||'Feld'));handle.className+=' fwb-drag';handle.draggable=false;
        const name=ui.el('span','fwb-card-name',b.label||catalog[b.type]||'Feld');
        const edit=button('✎',()=>select(b.id),'Bearbeiten: '+(b.label||catalog[b.type]||'Feld'));edit.className+=' fwb-edit';edit.title=edit.getAttribute('aria-label');
        const meta=document.createElement('small');meta.textContent=(b.type==='field'?catalog[b.field.type+'_field']:catalog[b.type])+(mandatory(b)?' · Pflicht':'')+(b.hidden?' · Ausgeblendet':'');
        const actions=document.createElement('div');actions.className='fwb-card-actions';
        const up=button('↑',()=>move(b.id,zone,index-1),'Nach oben: '+(b.label||b.type));up.disabled=index===0;
        const down=button('↓',()=>move(b.id,zone,index+2),'Nach unten: '+(b.label||b.type));down.disabled=index===layout.zones[zone].length-1;
        const location=document.createElement('select');location.setAttribute('aria-label','Bereich: '+(b.label||b.type));Object.entries(zones).forEach(([k,v])=>{const o=document.createElement('option');o.value=k;o.textContent=v;location.append(o);});location.value=zone;location.addEventListener('change',()=>move(b.id,location.value,layout.zones[location.value].length));
        actions.append(up,down,location);
        if(!mandatory(b)){
          actions.append(button(b.hidden?'Einblenden':'Ausblenden',()=>{b.hidden=!b.hidden;change();renderCards();}));
          if(['text','heading','field'].includes(b.type))actions.append(button('Duplizieren',()=>{
            flush();const copy=JSON.parse(JSON.stringify(b));copy.id='copy_'+Date.now().toString(36);
            if(copy.advanced)copy.advanced.html_id='';
            if(copy.field)copy.field.key='feld_'+Date.now().toString(36);
            layout.zones[zone].splice(index+1,0,copy);selected=copy.id;change();renderCards();inspect();
          }));
        }
        if(!mandatory(b))actions.append(button('Entfernen',()=>{flush();layout.zones[zone].splice(index,1);if(selected===b.id)selected=null;change();renderCards();inspect();},'Entfernen: '+(b.label||b.type)));
        row.append(handle,name,edit,meta);
        if(['text','conditions'].includes(b.type)) { const preview=document.createElement('p');preview.className='fwb-card-preview';preview.textContent=b.content.replace(/<[^>]*>/g,' ').slice(0,90);row.append(preview); }
        if(!ui.cfg.translation)row.append(actions);else handle.disabled=true;list.append(row);
      });
    });
    palette.querySelectorAll('[data-palette]').forEach(b=>{b.disabled=ui.cfg.translation||singleton(b.dataset.palette)&&all().some(item=>item.type===b.dataset.palette);});
  };
  let pointerDrag=null, suppressClick=false, dropTarget=null;
  const clearTarget=()=>{dropTarget?.classList.remove('fwb-drop-target');dropTarget=null;};
  host.addEventListener('pointerdown',e=>{
    const source=e.target.closest('[data-palette], .fwb-drag');
    if(!source||source.disabled||e.button!==0)return;
    pointerDrag={x:e.clientX,y:e.clientY,source,active:false,id:e.pointerId,
      payload:source.dataset.palette?{type:source.dataset.palette}:{id:source.closest('[data-card]').dataset.card}};
    source.setPointerCapture(e.pointerId);
  });
  host.addEventListener('pointermove',e=>{
    if(!pointerDrag||e.pointerId!==pointerDrag.id)return;
    if(!pointerDrag.active&&Math.hypot(e.clientX-pointerDrag.x,e.clientY-pointerDrag.y)>6){pointerDrag.active=true;flush();host.classList.add('is-dragging');}
    if(!pointerDrag.active)return;
    e.preventDefault();clearTarget();
    const hit=document.elementFromPoint(e.clientX,e.clientY);
    dropTarget=hit?.closest('[data-card], [data-zone]');
    dropTarget?.classList.add('fwb-drop-target');
    if(e.clientY<65)window.scrollBy(0,-18);
    else if(e.clientY>window.innerHeight-45)window.scrollBy(0,18);
  });
  host.addEventListener('pointerup',e=>{
    if(!pointerDrag||e.pointerId!==pointerDrag.id)return;
    const state=pointerDrag;pointerDrag=null;host.classList.remove('is-dragging');
    const hit=document.elementFromPoint(e.clientX,e.clientY);
    const zone=hit?.closest('[data-zone]')?.dataset.zone;
    const card=hit?.closest('[data-card]');
    const index=card?locate(card.dataset.card)?.index:zone?layout.zones[zone].length:0;
    clearTarget();
    if(state.active){
      suppressClick=true;setTimeout(()=>suppressClick=false,0);
      if(zone){if(state.payload.id)move(state.payload.id,zone,index);else add(state.payload.type,zone,index);}
    }
  });
  host.addEventListener('pointercancel',()=>{pointerDrag=null;clearTarget();host.classList.remove('is-dragging');});
  host.addEventListener('click',e=>{if(suppressClick){e.preventDefault();e.stopImmediatePropagation();}},true);
  if(ui.cfg.translation){
    dest.disabled=true;
    const note=ui.el('p','description','Du übersetzt die Inhalte. Neue Elemente, Reihenfolge und Design werden für alle Sprachen in der Ausgangssprache gepflegt.');
    const sourceLink=ui.el('a','button','Aufbau bearbeiten');sourceLink.href=ui.cfg.sourceEditor;
    title.after(note,sourceLink);
  }
  shell.append(palette,canvas);host.append(shell);renderCards();sync();
  richArea.addEventListener('input',()=>{flush();change();});
  const attachEditor = ed => {
    if(ed.id!=='fwb_rich_content'||ed.fwbBuilderBound)return;ed.fwbBuilderBound=true;
    ed.on('change input undo redo',()=>{flush();change();});
    ed.on('init',()=>{if(isRich(current()))ed.setContent(current().content);});
  };
  if(window.jQuery)jQuery(document).on('tinymce-editor-init.fwbBuilder',(_,ed)=>attachEditor(ed));
  if(window.tinymce){window.tinymce.on('AddEditor',e=>attachEditor(e.editor));if(editor())attachEditor(editor());}
  host.closest('form').addEventListener('submit',async e=>{
    e.preventDefault();if(saving)return;flush();
    const bad=all().find(b=>
      (required.includes(b.type)&&b.type!=='quote'&&!b.label.trim()) ||
      (b.type==='consent'&&!b.label.includes('{privacy_link}')) ||
      (b.type==='field'&&(!b.label.trim()||!/^[a-z][a-z0-9_]{0,39}$/.test(b.field.key)||(b.field.type==='select'&&!String(b.field.options).trim()))) ||
      (b.type==='quote'&&(!b.summary_template?.includes('{total}')||['{deposit}','{balance}','{local_tax}'].some(t=>!b.detail_template?.includes(t))))
    );
    if(bad){e.preventDefault();select(bad.id);status('Bitte Beschriftung, Kennung und erforderliche Platzhalter dieses Elements vervollständigen.');return;}
    status('Wird gespeichert …');
    const submitted=JSON.stringify(layout);saving=true;
    try{
      const result=await ui.api('layout_save',JSON.parse(submitted));
      flush();
      if(JSON.stringify(layout)!==submitted){
        // Keep edits made while the request was in flight, including their control bindings.
        dirty=true;ui.storeDraft('layout',layout);
        status(result.message+' Neuere Änderungen sind noch nicht gespeichert.');
      }else{
        layout=result.layout;history=[JSON.stringify(layout)];historyPos=0;dirty=false;
        ui.clearDraft('layout');renderCards();inspect();sync();status(result.message);
      }
    }
    catch(error){status(error.message);ui.storeDraft('layout',layout);}
    finally{saving=false;}
  });
  const toolbar=document.querySelector('#fwb-studio-toolbar');
  const previewPanel=ui.el('div','fwb-live-preview');previewPanel.hidden=true;
  const iframe=ui.el('iframe');iframe.title='Formularvorschau';iframe.setAttribute('sandbox','allow-scripts allow-same-origin');iframe.style.width='1100px';previewPanel.append(iframe);canvas.after(previewPanel);
  const renderPreview=async()=>{
    flush();const revision=++previewVersion;status('Vorschau wird geladen …');
    const data=new FormData();data.set('nonce',ui.cfg.nonce);data.set('fwb_lang',ui.cfg.language);data.set('payload',JSON.stringify(layout));data.set('theme',theme.value);
    try{const response=await fetch(ui.cfg.preview,{method:'POST',body:data});const html=await response.text();if(!response.ok)throw Error(html.replace(/<[^>]*>/g,' '));if(revision!==previewVersion)return;iframe.srcdoc=html;status('Vorschau mit aktuellen, noch nicht gespeicherten Einstellungen.');}catch(e){status(e.message);}
  };
  const device=ui.el('select');device.setAttribute('aria-label','Vorschau-Gerät');Object.entries({'1100px':'Desktop','768px':'Tablet','390px':'Smartphone'}).forEach(([v,t])=>{const o=ui.el('option','',t);o.value=v;device.append(o);});device.addEventListener('change',()=>iframe.style.width=device.value);
  const theme=ui.el('select');theme.setAttribute('aria-label','Vorschau-Hintergrund');Object.entries({light:'Heller Bereich',dark:'Dunkler Bereich'}).forEach(([v,t])=>{const o=ui.el('option','',t);o.value=v;theme.append(o);});theme.addEventListener('change',()=>{if(previewMode)renderPreview();});
  toolbar.append(button('↶ Rückgängig',()=>{flush();change();if(historyPos>0){layout=JSON.parse(history[--historyPos]);selected=null;renderCards();inspect();sync();ui.storeDraft('layout',layout);status('Änderung rückgängig.');}}),
    button('↷ Wiederholen',()=>{if(historyPos<history.length-1){layout=JSON.parse(history[++historyPos]);selected=null;renderCards();inspect();sync();dirty=true;ui.storeDraft('layout',layout);status('Änderung wiederholt.');}}),
    button('Aufbau',()=>{previewMode=false;canvas.hidden=false;previewPanel.hidden=true;}),
    button('Vorschau aktualisieren',()=>{previewMode=true;canvas.hidden=true;previewPanel.hidden=false;renderPreview();}),device,theme);
  const draft=ui.getDraft('layout');if(draft)toolbar.append(button('Lokalen Entwurf laden',()=>{flush();layout=draft;selected=null;change();renderCards();inspect();}));
  toolbar.append(button('Baukasten speichern',()=>host.closest('form').requestSubmit(),'Baukasten speichern'));
  window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
