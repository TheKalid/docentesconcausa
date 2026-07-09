"use client";

type LegacyToolEmbedProps = {
  html: string;
  title: string;
};

export function LegacyToolEmbed({ html, title }: LegacyToolEmbedProps) {
  return (
    <div className="legacy-tool-embed -mx-5 w-[calc(100%+2.5rem)] max-w-none sm:mx-0 sm:w-full sm:max-w-none">
      <iframe
        title={title}
        srcDoc={html}
        className="min-h-[calc(100vh-8rem)] w-full border-0 bg-white"
        sandbox="allow-scripts allow-same-origin allow-forms allow-modals allow-popups allow-downloads"
      />
    </div>
  );
}
