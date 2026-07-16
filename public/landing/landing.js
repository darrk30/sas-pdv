(function(){
  const cv=document.getElementById('hero-canvas');
  if(!cv)return;
  const ctx=cv.getContext('2d');
  let W,H,nodes;
  function resize(){W=cv.width=cv.offsetWidth;H=cv.height=cv.offsetHeight;}
  function lerpColor(t){let r,g,b;if(t<0.5){const u=t*2;r=240+(245-240)*u;g=112+(184-112)*u;b=32+(0-32)*u;}else{const u=(t-0.5)*2;r=245+(0-245)*u;g=184+(151-184)*u;b=0+(181-0)*u;}return[Math.round(r),Math.round(g),Math.round(b)];}
  function init(){const N=Math.min(50,Math.floor(W*H/20000));nodes=Array.from({length:N},()=>({x:Math.random()*W,y:Math.random()*H,vx:(Math.random()-.5)*.3,vy:(Math.random()-.5)*.3,r:Math.random()*1.8+0.8,t:Math.random(),phase:Math.random()*Math.PI*2}));}
  function draw(){ctx.clearRect(0,0,W,H);nodes.forEach(n=>{n.x+=n.vx;n.y+=n.vy;n.phase+=.01;if(n.x<0||n.x>W)n.vx*=-1;if(n.y<0||n.y>H)n.vy*=-1;});for(let i=0;i<nodes.length;i++)for(let j=i+1;j<nodes.length;j++){const dx=nodes[i].x-nodes[j].x,dy=nodes[i].y-nodes[j].y,d=Math.sqrt(dx*dx+dy*dy);if(d<130){const[r,g,b]=lerpColor((nodes[i].t+nodes[j].t)/2);ctx.beginPath();ctx.moveTo(nodes[i].x,nodes[i].y);ctx.lineTo(nodes[j].x,nodes[j].y);ctx.strokeStyle=`rgba(${r},${g},${b},${(1-d/130)*.15})`;ctx.lineWidth=.8;ctx.stroke();}}nodes.forEach(n=>{const[r,g,b]=lerpColor(n.t);const pulse=Math.sin(n.phase)*.3+.7;const gr=ctx.createRadialGradient(n.x,n.y,0,n.x,n.y,n.r*5);gr.addColorStop(0,`rgba(${r},${g},${b},${.2*pulse})`);gr.addColorStop(1,`rgba(${r},${g},${b},0)`);ctx.beginPath();ctx.arc(n.x,n.y,n.r*5,0,Math.PI*2);ctx.fillStyle=gr;ctx.fill();ctx.beginPath();ctx.arc(n.x,n.y,n.r,0,Math.PI*2);ctx.fillStyle=`rgba(${r},${g},${b},${.55*pulse})`;ctx.fill();});requestAnimationFrame(draw);}
  resize();init();draw();
  window.addEventListener('resize',()=>{resize();init();});
})();

window.addEventListener('scroll',()=>{document.getElementById('nav').classList.toggle('scrolled',scrollY>10);},{passive:true});

document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{const el=document.getElementById(a.getAttribute('href').slice(1));if(!el)return;e.preventDefault();window.scrollTo({top:el.getBoundingClientRect().top+scrollY-70,behavior:'smooth'});});
});

(function(){
  const el=document.getElementById('typed');
  if(!el)return;
  const words=['tiendas de ropa','minimarkets','ferreterías','cafeterías','farmacias','bodegas','cualquier negocio'];
  let wi=0,ci=0,del=false;
  function tick(){const w=words[wi];if(!del){el.textContent=w.slice(0,++ci);if(ci===w.length){del=true;return setTimeout(tick,1800);}}else{el.textContent=w.slice(0,--ci);if(ci===0){del=false;wi=(wi+1)%words.length;}}setTimeout(tick,del?50:80);}
  tick();
})();

const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting)e.target.classList.add('visible');}),{threshold:.1});
document.querySelectorAll('.fade-up').forEach(el=>io.observe(el));

function toggleFaq(btn){const item=btn.closest('.faq-item');const isOpen=item.classList.contains('open');document.querySelectorAll('.faq-item.open').forEach(i=>{i.classList.remove('open');});if(!isOpen)item.classList.add('open');}
