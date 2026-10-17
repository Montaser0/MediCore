export function systemLogoUrl() {
  const path = "/logo.png?v=4";
  if (typeof window === "undefined") return path;
  return `${window.location.origin}${path}`;
}

export function printWhenReady(target: Window) {
  let printed = false;
  const run = () => {
    if (printed) return;
    printed = true;
    target.focus();
    target.print();
  };

  const pending = Array.from(target.document.images).filter((image) => !image.complete);
  if (pending.length === 0) {
    window.setTimeout(run, 200);
    return;
  }

  let left = pending.length;
  const done = () => {
    left -= 1;
    if (left <= 0) run();
  };
  pending.forEach((image) => {
    image.addEventListener("load", done);
    image.addEventListener("error", done);
  });
  window.setTimeout(run, 1500);
}
