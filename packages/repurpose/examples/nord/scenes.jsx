import React, {useEffect, useState} from 'react';
import {AbsoluteFill, Img, cancelRender, continueRender, delayRender, staticFile, useCurrentFrame} from 'remotion';

const cream = '#f2ecd9';
const pine = '#163b35';
const orange = '#e96839';
const clamp = (value) => Math.max(0, Math.min(1, value));
const reveal = (frame, from = 0, length = 24) => 1 - (1 - clamp((frame - from) / length)) ** 4;
let fonts;

const Shell = ({children, color = cream, background = pine}) => {
  const [handle] = useState(() => delayRender('NORD local fonts'));
  useEffect(() => {
    fonts ??= Promise.all([
      new FontFace('NordHeading', `url(${staticFile('fonts/montserrat.ttf')})`).load(),
      new FontFace('NordBody', `url(${staticFile('fonts/work-sans.ttf')})`).load(),
    ]).then((loaded) => loaded.forEach((font) => document.fonts.add(font)));
    fonts.then(() => continueRender(handle)).catch(cancelRender);
  }, [handle]);
  return <AbsoluteFill style={{background, color, overflow: 'hidden', fontFamily: 'NordBody'}}>{children}</AbsoluteFill>;
};

const Photo = ({src = 'aurora.jpg', style = {}}) => <Img src={staticFile(`photos/${src}`)}
  style={{position: 'absolute', width: '100%', height: '100%', objectFit: 'cover', ...style}}/>;

const Label = ({children, style = {}}) => <div style={{position: 'absolute', left: 76, top: 108,
  fontSize: 25, letterSpacing: 5, textTransform: 'uppercase', ...style}}>{children}</div>;

const Word = ({children, frame, from = 0, style = {}}) => <div style={{overflow: 'hidden'}}>
  <div style={{fontFamily: 'NordHeading', fontWeight: 700, lineHeight: 1.08,
    transform: `translateY(${(1 - reveal(frame, from)) * 115}%)`, ...style}}>{children}</div>
</div>;

const Opening = () => {
  const frame = useCurrentFrame();
  const line = reveal(frame, 22, 42);
  return <Shell>
    <Photo style={{transform: `scale(${1.12 - frame * 0.0005})`, objectPosition: '52% center'}}/>
    <AbsoluteFill style={{background: 'linear-gradient(180deg, #102b3566, transparent 45%, #102b35b8)'}}/>
    <Label style={{opacity: reveal(frame, 8)}}>Une parenthèse<br/>au grand air</Label>
    <div style={{position: 'absolute', left: 56, right: 40, top: 625}}>
      <Word frame={frame} from={12} style={{fontSize: 284, letterSpacing: -19}}>NORD.</Word>
    </div>
    <svg width="1080" height="1920" viewBox="0 0 1080 1920" style={{position: 'absolute'}}>
      <path d="M 78 1090 C 270 1020 335 1190 510 1125 S 820 1040 1000 1100" pathLength="1"
        fill="none" stroke={orange} strokeWidth="7" strokeLinecap="round" strokeDasharray="1" strokeDashoffset={1 - line}/>
      <circle cx="78" cy="1090" r={9 * line} fill={orange}/>
    </svg>
    <Label style={{top: 'auto', bottom: 190, opacity: reveal(frame, 36), letterSpacing: 2}}>Laisser une place<br/>à l’inattendu.</Label>
    <Label style={{left: 'auto', right: 76, top: 'auto', bottom: 84, fontSize: 19, letterSpacing: 3}}>PUSHWORD / MOTION STUDY 01</Label>
  </Shell>;
};

const Route = () => {
  const frame = useCurrentFrame();
  const progress = reveal(frame, 18, 68);
  return <Shell background={cream} color={pine}>
    <Label>01 / Prendre le temps</Label>
    <div style={{position: 'absolute', top: 270, left: 76, fontSize: 142, letterSpacing: -7}}>
      <Word frame={frame} from={5}>Sortir</Word><Word frame={frame} from={12}>du cadre.</Word>
    </div>
    <div style={{position: 'absolute', right: -105, top: 745, width: 650, height: 840,
      borderRadius: '340px 340px 0 0', overflow: 'hidden', transform: `translateX(${(1 - reveal(frame, 8, 36)) * 250}px) rotate(9deg)`}}>
      <Photo style={{objectPosition: '65% center', transform: `scale(${1.04 + frame * 0.0003})`}}/>
    </div>
    <svg width="1080" height="1920" viewBox="0 0 1080 1920" style={{position: 'absolute'}}>
      <path d="M 132 805 C 365 805 390 1050 190 1120 S 100 1390 350 1490" fill="none" stroke="#b4b8a9" strokeWidth="3" strokeDasharray="6 12"/>
      <path d="M 132 805 C 365 805 390 1050 190 1120 S 100 1390 350 1490" pathLength="1"
        fill="none" stroke={orange} strokeWidth="7" strokeLinecap="round" strokeDasharray="1" strokeDashoffset={1 - progress}/>
      {[[132,805,18],[190,1120,48],[350,1490,78]].map(([cx,cy,start],i) =>
        <g key={i} opacity={reveal(frame,start,10)}><circle cx={cx} cy={cy} r="20" fill={cream} stroke={orange} strokeWidth="6"/></g>)}
    </svg>
    {[[190,745,'Marcher.',22],[270,1090,'Respirer.',52],[80,1580,'Ralentir.',82]].map(([left,top,text,start]) =>
      <div key={text} style={{position: 'absolute', left, top, fontSize: 37, opacity: reveal(frame,start,16)}}>{text}</div>)}
    <Label style={{top: 'auto', bottom: 86, fontSize: 20, letterSpacing: 2}}>Un chemin. Pas une course.</Label>
  </Shell>;
};

const Shelter = () => {
  const frame = useCurrentFrame();
  const wipe = reveal(frame, 0, 30);
  return <Shell>
    <Photo src="camp.jpg" style={{transform: `scale(${1.11 - frame * 0.00035})`, clipPath: `inset(0 ${(1-wipe)*100}% 0 0)`}}/>
    <AbsoluteFill style={{background: 'linear-gradient(180deg, #102b3599, transparent 56%, #102b35b3)'}}/>
    <Label style={{opacity: reveal(frame, 9)}}>02 / Habiter la nuit</Label>
    <div style={{position: 'absolute', top: 285, left: 76, fontSize: 112, letterSpacing: -5}}>
      <Word frame={frame} from={14}>La nuit nous</Word><Word frame={frame} from={23}>appartient.</Word>
    </div>
    <svg width="1080" height="1920" viewBox="0 0 1080 1920" style={{position: 'absolute'}}>
      <path d="M 720 660 L 815 710 L 770 830 L 920 870" pathLength="1" fill="none" stroke={cream}
        strokeWidth="2" opacity=".6" strokeDasharray="1" strokeDashoffset={1-reveal(frame,35,45)}/>
      {[[720,660,35],[815,710,48],[770,830,62],[920,870,78]].map(([cx,cy,start]) =>
        <g key={cx} opacity={reveal(frame,start,10)}><path d={`M ${cx-10} ${cy} H ${cx+10} M ${cx} ${cy-10} V ${cy+10}`} stroke={cream} strokeWidth="3"/></g>)}
    </svg>
    <Label style={{top:'auto', bottom:180, opacity:reveal(frame,48), fontSize:30, letterSpacing:2}}>Couper le bruit.<br/>Garder l’essentiel.</Label>
  </Shell>;
};

const Rhythm = () => {
  const frame = useCurrentFrame();
  return <Shell background={orange} color={pine}>
    <Label>03 / Faire de la place</Label>
    <div style={{position:'absolute', left:76, top:400, fontSize:105, letterSpacing:-5, lineHeight:1.35}}>
      {['Explorer.', 'Ressentir.', 'Recommencer.'].map((text,i) =>
        <div key={text} style={{marginBottom:28}}><Word frame={frame} from={i*12+5} style={{color:i===1?cream:pine}}>{text}</Word></div>)}
    </div>
    <div style={{position:'absolute', left:76, right:76, top:1020, height:520,
      overflow:'hidden', clipPath:`inset(0 ${(1-reveal(frame,25,30))*100}% 0 0)`}}>
      <Photo style={{objectPosition:'center 65%', transform:`scale(${1.2-frame*.001})`}}/>
    </div>
    <Label style={{top:'auto', bottom:160, letterSpacing:2, fontSize:28, opacity:reveal(frame,48)}}>Le plus beau programme :<br/>ne pas tout prévoir.</Label>
  </Shell>;
};

const Closing = () => {
  const frame = useCurrentFrame();
  return <Shell background={cream} color={pine}>
    <div style={{position:'absolute', width:720, height:720, left:180, top:-185,
      borderRadius:'50%', overflow:'hidden', transform:`translateY(${(1-reveal(frame,0,36))*-240}px)`}}>
      <Photo style={{objectPosition:'center 30%', transform:`scale(${1.1-frame*.0004})`}}/>
    </div>
    <div style={{position:'absolute', top:680, left:76, fontSize:122, letterSpacing:-6}}>
      <Word frame={frame} from={14}>Le monde</Word><Word frame={frame} from={23}>est dehors.</Word>
    </div>
    <div style={{position:'absolute', left:80, top:1070, fontSize:36, opacity:reveal(frame,44)}}>À vous d’écrire la suite.</div>
    <svg width="1080" height="1920" viewBox="0 0 1080 1920" style={{position:'absolute'}}>
      <g transform={`translate(${(1-reveal(frame,48,25))*-70} 0)`} opacity={reveal(frame,48)}>
        <circle cx="176" cy="1370" r="96" fill={orange}/>
        <path d="M 130 1370 H 220 M 190 1340 L 220 1370 L 190 1400" fill="none" stroke={pine} strokeWidth="7" strokeLinecap="round" strokeLinejoin="round"/>
      </g>
    </svg>
    <Label style={{top:'auto', bottom:150, fontFamily:'NordHeading', fontSize:56, letterSpacing:-2}}>NORD.</Label>
    <Label style={{top:'auto', bottom:86, fontSize:19, letterSpacing:3}}>PUSHWORD REPURPOSE / MOTION STUDY 01</Label>
  </Shell>;
};

export const scenes = {'slide-1':Opening, 'slide-2':Route, 'slide-3':Shelter, 'slide-4':Rhythm, 'slide-5':Closing};
