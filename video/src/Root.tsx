import { Composition } from "remotion";
import { Bumper } from "./Bumper";
import timeline from "./timeline.json";

export const Root = () => (
  <Composition
    id="Bumper"
    component={Bumper}
    durationInFrames={timeline.total}
    fps={timeline.fps}
    width={1920}
    height={1080}
  />
);
