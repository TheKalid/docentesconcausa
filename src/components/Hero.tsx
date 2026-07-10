"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

const BANNER_SLIDES = [
  {
    src: "/banner/banner_1.png",
    alt: "Docentes colaborando con mapas y material educativo",
  },
  {
    src: "/banner/banner_2.png",
    alt: "Mentoría docente en un ambiente de aprendizaje",
  },
  {
    src: "/banner/banner_3.png",
    alt: "Estudiantes y docentes trabajando con robótica educativa",
  },
] as const;

const SLIDE_INTERVAL_MS = 6000;

export function Hero() {
  const [activeSlide, setActiveSlide] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => {
      setActiveSlide((current) => (current + 1) % BANNER_SLIDES.length);
    }, SLIDE_INTERVAL_MS);

    return () => window.clearInterval(timer);
  }, []);

  return (
    <section className="px-5 pb-16 pt-10 md:pb-20 md:pt-12">
      <div className="relative mx-auto max-w-[1080px] overflow-hidden rounded-[16px] border border-black/[0.08] shadow-[var(--shadow-panel)]">
        <div className="relative min-h-[520px] md:min-h-[560px]">
          {BANNER_SLIDES.map((slide, index) => (
            <div
              key={slide.src}
              className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
                index === activeSlide ? "opacity-100" : "opacity-0"
              }`}
              aria-hidden={index !== activeSlide}
            >
              <img
                src={slide.src}
                alt={slide.alt}
                className="h-full w-full object-cover"
                decoding="async"
              />
            </div>
          ))}

          <div
            className="absolute inset-0 bg-gradient-to-r from-black/75 via-black/55 to-black/35"
            aria-hidden="true"
          />
          <div
            className="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/20"
            aria-hidden="true"
          />

          <div className="relative flex min-h-[520px] flex-col items-center justify-center px-6 py-16 text-center md:min-h-[560px] md:px-12 md:py-20">
            <p className="mb-4 text-[10px] font-[500] uppercase tracking-[0.16em] text-white/75">
              Plataforma docente con inteligencia artificial
            </p>

            <h1 className="mb-6 max-w-[760px] text-[36px] font-[400] leading-[1.02] tracking-[-0.9px] text-white md:text-[56px] md:tracking-[-1.2px]">
              Herramientas para docentes que planean con propósito
            </h1>

            <p className="mb-10 max-w-[620px] text-[16px] leading-[1.55] text-white/85 md:text-[18px] md:leading-[1.5]">
              Genera planeaciones, evaluaciones, exámenes y materiales alineados
              a la NEM. Todo en un solo lugar, diseñado para ahorrarte tiempo y
              fortalecer tu práctica en el aula.
            </p>

            <div className="flex w-full max-w-[640px] flex-col gap-4 sm:flex-row sm:justify-center">
              <Link
                href="/registro"
                className="rounded-full bg-signal-blue px-8 py-4 text-center text-[16px] font-[500] tracking-[-0.32px] text-white shadow-[0_10px_30px_rgba(37,99,235,0.5)] transition hover:scale-[1.03] hover:bg-[#1d4ed8] hover:shadow-[0_14px_36px_rgba(37,99,235,0.6)]"
              >
                Regístrate aquí
              </Link>
              <Link
                href="/planes"
                className="rounded-full bg-[#f59e0b] px-8 py-4 text-center text-[16px] font-[500] tracking-[-0.32px] text-white shadow-[0_10px_30px_rgba(245,158,11,0.45)] transition hover:scale-[1.03] hover:bg-[#d97706] hover:shadow-[0_14px_36px_rgba(245,158,11,0.55)]"
              >
                Ver Planes de Suscripción
              </Link>
            </div>
          </div>

          <div className="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2">
            {BANNER_SLIDES.map((slide, index) => (
              <button
                key={slide.src}
                type="button"
                aria-label={`Ir a la imagen ${index + 1}`}
                aria-current={index === activeSlide ? "true" : undefined}
                onClick={() => setActiveSlide(index)}
                className={`h-2.5 rounded-full transition-all ${
                  index === activeSlide
                    ? "w-8 bg-white"
                    : "w-2.5 bg-white/45 hover:bg-white/70"
                }`}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
