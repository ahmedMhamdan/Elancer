import { Link } from '@inertiajs/react';
import { ArrowDown, Sparkles } from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type HeroButton = {
    text: string;
    href?: string;
    onClick?: () => void;
};

export interface HeroProps {
    trustBadge?: { text: string; icons?: string[] };
    headline: { line1: string; line2: string };
    subtitle: string;
    buttons?: { primary?: HeroButton; secondary?: HeroButton };
    className?: string;
    children?: ReactNode;
}

const vertexSource = `#version 300 es
in vec2 position;
void main() { gl_Position = vec4(position, 0., 1.); }`;

const defaultShaderSource = "#version 300 es\n// Original shader supplied by the user, made by Matthias Hurrle (@atzedent).\n// \"To explore strange new worlds, to seek out new life and new civilizations,\n// to boldly go where no man has gone before.\"\nprecision highp float;\nout vec4 O;\nuniform vec2 resolution;\nuniform float time;\n#define FC gl_FragCoord.xy\n#define T time\n#define R resolution\n#define MN min(R.x,R.y)\nfloat rnd(vec2 p) {\n    p=fract(p*vec2(12.9898,78.233));\n    p+=dot(p,p+34.56);\n    return fract(p.x*p.y);\n}\nfloat noise(in vec2 p) {\n    vec2 i=floor(p), f=fract(p), u=f*f*(3.-2.*f);\n    float a=rnd(i), b=rnd(i+vec2(1,0)), c=rnd(i+vec2(0,1)), d=rnd(i+1.);\n    return mix(mix(a,b,u.x),mix(c,d,u.x),u.y);\n}\nfloat fbm(vec2 p) {\n    float t=.0, a=1.; mat2 m=mat2(1.,-.5,.2,1.2);\n    for (int i=0; i<5; i++) {\n        t+=a*noise(p);\n        p*=2.*m;\n        a*=.5;\n    }\n    return t;\n}\nfloat clouds(vec2 p) {\n    float d=1., t=.0;\n    for (float i=.0; i<3.; i++) {\n        float a=d*fbm(i*10.+p.x*.2+.2*(1.+i)*p.y+d+i*i+p);\n        t=mix(t,d,a);\n        d=a;\n        p*=2./(i+1.);\n    }\n    return t;\n}\nvoid main(void) {\n    vec2 uv=(FC-.5*R)/MN,st=uv*vec2(2,1);\n    vec3 col=vec3(0);\n    float bg=clouds(vec2(st.x+T*.5,-st.y));\n    uv*=1.-.3*(sin(T*.2)*.5+.5);\n    for (float i=1.; i<12.; i++) {\n        uv+=.1*cos(i*vec2(.1+.01*i, .8)+i*i+T*.5+.1*uv.x);\n        vec2 p=uv;\n        float d=max(length(p),.0001);\n        col+=.00125/d*(cos(sin(i)*vec3(1,2,3))+1.);\n        float b=noise(i+p+bg*1.731);\n        col+=.002*b/max(length(max(p,vec2(b*p.x*.02,p.y))),.0001);\n        col=mix(col,vec3(bg*.25,bg*.137,bg*.05),d);\n    }\n    float glow = dot(col, vec3(.299, .587, .114));\n    O=vec4(glow * vec3(87., 210., 120.) / 160., 1.);\n}";

// The supplied shader only uses time and resolution, so no pointer listeners
// or external animation dependencies are necessary.
export function useShaderBackground() {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const gl = canvas.getContext('webgl2', {
            alpha: false,
            antialias: false,
            powerPreference: 'low-power',
        });
        // The CSS background stays visible when WebGL2 is unavailable.
        if (!gl) return;

        const vertex = gl.createShader(gl.VERTEX_SHADER);
        const fragment = gl.createShader(gl.FRAGMENT_SHADER);
        const program = gl.createProgram();
        const buffer = gl.createBuffer();
        const array = gl.createVertexArray();
        const release = () => {
            gl.deleteBuffer(buffer);
            gl.deleteVertexArray(array);
            gl.deleteProgram(program);
            gl.deleteShader(vertex);
            gl.deleteShader(fragment);
        };

        if (!vertex || !fragment || !program || !buffer || !array) {
            release();
            return;
        }

        gl.shaderSource(vertex, vertexSource);
        gl.compileShader(vertex);
        gl.shaderSource(fragment, defaultShaderSource);
        gl.compileShader(fragment);

        if (!gl.getShaderParameter(vertex, gl.COMPILE_STATUS) ||
            !gl.getShaderParameter(fragment, gl.COMPILE_STATUS)) {
            release();
            return;
        }

        gl.attachShader(program, vertex);
        gl.attachShader(program, fragment);
        gl.linkProgram(program);
        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            release();
            return;
        }

        gl.bindVertexArray(array);
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, 1, -1, -1, 1, 1, 1, -1]), gl.STATIC_DRAW);
        const position = gl.getAttribLocation(program, 'position');
        gl.enableVertexAttribArray(position);
        gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);
        const resolution = gl.getUniformLocation(program, 'resolution');
        const time = gl.getUniformLocation(program, 'time');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let frame = 0;
        let lastFrame = 0;
        let inView = true;
        let lost = false;
        const started = performance.now();

        const draw = (now: number) => {
            if (lost) return;
            gl.useProgram(program);
            gl.bindVertexArray(array);
            gl.uniform2f(resolution, canvas.width, canvas.height);
            gl.uniform1f(time, motion.matches ? 18 : 18 + (now - started) * .001);
            gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
            canvas.style.opacity = '1';
        };

        const resize = () => {
            const { width, height } = canvas.getBoundingClientRect();
            // Bound GPU work on large and high-density screens.
            const ratio = Math.min(window.devicePixelRatio || 1, 1.25, 1440 / Math.max(width, 1));
            canvas.width = Math.max(1, Math.round(width * ratio));
            canvas.height = Math.max(1, Math.round(height * ratio));
            gl.viewport(0, 0, canvas.width, canvas.height);
            draw(performance.now());
        };

        const loop = (now: number) => {
            if (now - lastFrame >= 1000 / 30) {
                draw(now);
                lastFrame = now;
            }
            frame = requestAnimationFrame(loop);
        };

        const sync = () => {
            cancelAnimationFrame(frame);
            if (lost || document.hidden || !inView) return;
            if (motion.matches) draw(performance.now());
            else frame = requestAnimationFrame(loop);
        };
        const onContextLost = (event: Event) => {
            event.preventDefault();
            lost = true;
            cancelAnimationFrame(frame);
            canvas.style.opacity = '0';
        };
        const observer = new ResizeObserver(resize);
        const intersection = new IntersectionObserver(([entry]) => {
            inView = entry.isIntersecting;
            sync();
        });
        observer.observe(canvas);
        intersection.observe(canvas);
        motion.addEventListener('change', sync);
        document.addEventListener('visibilitychange', sync);
        canvas.addEventListener('webglcontextlost', onContextLost);
        resize();
        sync();

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            intersection.disconnect();
            motion.removeEventListener('change', sync);
            document.removeEventListener('visibilitychange', sync);
            canvas.removeEventListener('webglcontextlost', onContextLost);
            release();
        };
    }, []);

    return canvasRef;
}

function HeroAction({ action, primary }: { action: HeroButton; primary?: boolean }) {
    const className = cn('elancer-action', primary ? 'elancer-action-primary' : 'elancer-action-secondary');
    const content = action.text;

    if (action.href?.startsWith('#')) {
        return <a href={action.href} className={className} onClick={action.onClick}>{content}</a>;
    }
    if (action.href) {
        return <Link href={action.href} className={className} onClick={action.onClick}>{content}</Link>;
    }
    return <button type="button" className={className} onClick={action.onClick}>{content}</button>;
}

export default function Hero({ trustBadge, headline, subtitle, buttons, className, children }: HeroProps) {
    const canvasRef = useShaderBackground();

    return (
        <section className={cn('elancer-hero', className)} aria-labelledby="hero-heading">
            <canvas ref={canvasRef} className="elancer-shader" aria-hidden="true" />
            <div className="elancer-hero-shade" aria-hidden="true" />
            {children}
            <div className="elancer-hero-content">
                {trustBadge && (
                    <div className="elancer-badge elancer-reveal">
                        {trustBadge.icons?.length ? trustBadge.icons.map((icon, index) => (
                            <span key={index} className="text-emerald-200" aria-hidden="true">{icon}</span>
                        )) : <Sparkles size={14} className="text-emerald-200" aria-hidden="true" />}
                        {trustBadge.text}
                    </div>
                )}
                <h1 id="hero-heading" className="elancer-headline elancer-reveal elancer-delay-1">
                    <span>{headline.line1}</span>
                    <span className="elancer-headline-accent">{headline.line2}</span>
                </h1>
                <p className="elancer-subtitle elancer-reveal elancer-delay-2">{subtitle}</p>
                {buttons && (
                    <div className="elancer-hero-actions elancer-reveal elancer-delay-3">
                        {buttons.primary && <HeroAction action={buttons.primary} primary />}
                        {buttons.secondary && <HeroAction action={buttons.secondary} />}
                    </div>
                )}
                <p className="elancer-hero-note elancer-reveal elancer-delay-3">
                    Your ideas. Your people. Your next chapter.
                </p>
            </div>
            <div className="elancer-hero-bottom">
                <span>A little ambition goes a long way.</span>
                <a href="#why-elancer">Discover a different way to work <ArrowDown size={14} aria-hidden="true" /></a>
            </div>
        </section>
    );
}
