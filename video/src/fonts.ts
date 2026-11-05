import { loadFont } from "@remotion/fonts";
import { staticFile } from "remotion";

const weights: [string, string][] = [
  ["400", "Inter-Regular.otf"],
  ["500", "Inter-Medium.otf"],
  ["600", "Inter-SemiBold.otf"],
  ["700", "Inter-Bold.otf"],
];

export const fontsReady = Promise.all(
  weights.map(([weight, file]) =>
    loadFont({ family: "Inter", url: staticFile(`fonts/${file}`), weight }),
  ),
);
