(function(l,J,u,m,K){function y(a){a=a.split(")");var b=l.trim,f=-1,e=a.length-1,d,h,g=z?new Float32Array(6):[],c=z?new Float32Array(6):[],k=z?new Float32Array(6):[1,0,0,1,0,0];g[0]=g[3]=k[0]=k[3]=1;for(g[1]=g[2]=g[4]=g[5]=0;++f<e;){d=a[f].split("(");h=b(d[0]);d=d[1];c[0]=c[3]=1;c[1]=c[2]=c[4]=c[5]=0;switch(h){case r+"X":c[4]=parseInt(d,10);break;case r+"Y":c[5]=parseInt(d,10);break;case r:d=d.split(",");c[4]=parseInt(d[0],10);c[5]=parseInt(d[1]||0,10);break;case v:d=w(d);c[0]=m.cos(d);c[1]=m.sin(d);
c[2]=-m.sin(d);c[3]=m.cos(d);break;case p+"X":c[0]=+d;break;case p+"Y":c[3]=d;break;case p:d=d.split(",");c[0]=d[0];c[3]=1<d.length?d[1]:d[0];break;case s+"X":c[2]=m.tan(w(d));break;case s+"Y":c[1]=m.tan(w(d));break;case x:d=d.split(","),c[0]=d[0],c[1]=d[1],c[2]=d[2],c[3]=d[3],c[4]=parseInt(d[4],10),c[5]=parseInt(d[5],10)}k[0]=g[0]*c[0]+g[2]*c[1];k[1]=g[1]*c[0]+g[3]*c[1];k[2]=g[0]*c[2]+g[2]*c[3];k[3]=g[1]*c[2]+g[3]*c[3];k[4]=g[0]*c[4]+g[2]*c[5]+g[4];k[5]=g[1]*c[4]+g[3]*c[5]+g[5];g=[k[0],k[1],k[2],
k[3],k[4],k[5]]}return k}function A(a){var b,f,e,d=a[0],h=a[1],g=a[2],c=a[3];d*c-h*g?(b=m.sqrt(d*d+h*h),d/=b,h/=b,e=d*g+h*c,g-=d*e,c-=h*e,f=m.sqrt(g*g+c*c),e/=f,c/f*d<g/f*h&&(d=-d,h=-h,e=-e,b=-b)):b=f=e=0;return[[r,[+a[4],+a[5]]],[v,m.atan2(h,d)],[s+"X",m.atan(e)],[p,[b,f]]]}function L(a,b){var f={start:[],end:[]},e=-1,d,h,g,c;("none"==a||G.test(a))&&(a="");("none"==b||G.test(b))&&(b="");a&&b&&!b.indexOf("matrix")&&B(a).join()==B(b.split(")")[0]).join()&&(f.origin=a,a="",b=b.slice(b.indexOf(")")+
1));if(a||b){if(a&&b&&a.replace(/(?:\([^)]*\))|\s/g,"")!=b.replace(/(?:\([^)]*\))|\s/g,""))f.start=A(y(a)),f.end=A(y(b));else for(a&&(a=a.split(")"))&&(d=a.length),b&&(b=b.split(")"))&&(d=b.length);++e<d-1;){a[e]&&(h=a[e].split("("));b[e]&&(g=b[e].split("("));c=l.trim((h||g)[0]);for(var k=f.start,m=H(c,h?h[1]:0),n=void 0;n=m.shift();)k.push(n);k=f.end;c=H(c,g?g[1]:0);for(m=void 0;m=c.shift();)k.push(m)}return f}}function H(a,b){var f=+!a.indexOf(p),e,d=a.replace(/e[XY]/,"e");switch(a){case r+"Y":case p+
"Y":b=[f,b?parseFloat(b):f];break;case r+"X":case r:case p+"X":e=1;case p:b=b?(b=b.split(","))&&[parseFloat(b[0]),parseFloat(1<b.length?b[1]:a==p?e||b[0]:f+"")]:[f,f];break;case s+"X":case s+"Y":case v:b=b?w(b):0;break;case x:return A(b?B(b):[1,0,0,1,0,0])}return[[d,b]]}function w(a){return~a.indexOf("deg")?parseInt(a,10)*(2*m.PI/360):~a.indexOf("grad")?parseInt(a,10)*(m.PI/200):parseFloat(a)}function B(a){a=/([^,]*),([^,]*),([^,]*),([^,]*),([^,p]*)(?:px)?,([^)p]*)(?:px)?/.exec(a);return[a[1],a[2],
a[3],a[4],a[5],a[6]]}u=u.createElement("div").style;var C=["OTransform","msTransform","WebkitTransform","MozTransform"],D=C.length,n,E,z="Float32Array"in J,q,I,F=/Matrix([^)]*)/,G=/^\s*matrix\(\s*1\s*,\s*0\s*,\s*0\s*,\s*1\s*(?:,\s*0(?:px)?\s*){2}\)\s*$/,r="translate",v="rotate",p="scale",s="skew",x="matrix";for(;D--;)C[D]in u&&(l.support.transform=n=C[D],l.support.transformOrigin=n+"Origin");n||(l.support.matrixFilter=E=""===u.filter);l.cssNumber.transform=l.cssNumber.transformOrigin=!0;n&&"transform"!=
n?(l.cssProps.transform=n,l.cssProps.transformOrigin=n+"Origin","MozTransform"==n?q={get:function(a,b){return b?l.css(a,n).split("px").join(""):a.style[n]},set:function(a,b){a.style[n]=/matrix\([^)p]*\)/.test(b)?b.replace(/matrix((?:[^,]*,){4})([^,]*),([^)]*)/,x+"$1$2px,$3px"):b}}:/^1\.[0-5](?:\.|$)/.test(l.fn.jquery)&&(q={get:function(a,b){return b?l.css(a,n.replace(/^ms/,"Ms")):a.style[n]}})):E&&(q={get:function(a,b,f){var e=b&&a.currentStyle?a.currentStyle:a.style;e&&F.test(e.filter)?(b=RegExp.$1.split(","),
b=[b[0].split("=")[1],b[2].split("=")[1],b[1].split("=")[1],b[3].split("=")[1]]):b=[1,0,0,1];l.cssHooks.transformOrigin?(a=l._data(a,"transformTranslate",K),b[4]=a?a[0]:0,b[5]=a?a[1]:0):(b[4]=e?parseInt(e.left,10)||0:0,b[5]=e?parseInt(e.top,10)||0:0);return f?b:x+"("+b+")"},set:function(a,b,f){var e=a.style,d,h;f||(e.zoom=1);b=y(b);f=["Matrix(M11="+b[0],"M12="+b[2],"M21="+b[1],"M22="+b[3],"SizingMethod='auto expand'"].join();h=(d=a.currentStyle)&&d.filter||e.filter||"";e.filter=F.test(h)?h.replace(F,
f):h+" progid:DXImageTransform.Microsoft."+f+")";if(l.cssHooks.transformOrigin)l.cssHooks.transformOrigin.set(a,b);else{if(d=l.transform.centerOrigin)e["margin"==d?"marginLeft":"left"]=-(a.offsetWidth/2)+a.clientWidth/2+"px",e["margin"==d?"marginTop":"top"]=-(a.offsetHeight/2)+a.clientHeight/2+"px";e.left=b[4]+"px";e.top=b[5]+"px"}}});q&&(l.cssHooks.transform=q);I=q&&q.get||l.css;l.fx.step.transform=function(a){var b=a.elem,f=a.start,e=a.end,d=a.pos,h="",g,c,k,t;f&&"string"!==typeof f||(f||(f=I(b,
n)),E&&(b.style.zoom=1),e=e.split("+=").join(f),l.extend(a,L(f,e)),f=a.start,e=a.end);for(g=f.length;g--;)switch(c=f[g],k=e[g],t=0,c[0]){case r:t="px";case p:t||(t="");h=c[0]+"("+m.round(1E5*(c[1][0]+(k[1][0]-c[1][0])*d))/1E5+t+","+m.round(1E5*(c[1][1]+(k[1][1]-c[1][1])*d))/1E5+t+")"+h;break;case s+"X":case s+"Y":case v:h=c[0]+"("+m.round(1E5*(c[1]+(k[1]-c[1])*d))/1E5+"rad)"+h}a.origin&&(h=a.origin+h);q&&q.set?q.set(b,h,1):b.style[n]=h};l.transform={centerOrigin:"margin"}})(jQuery,window,document,
Math);var transform2Dloaded=!0;