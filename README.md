# Video Annotation

`mod_videoannotation` is a Moodle activity that lets learners attach notes to exact moments or intervals of a video.

## How it works

Students can create point annotations, mark intervals, classify notes and answer teacher-defined annotation prompts.
Clicking an annotation returns the player to the referenced moment, so the notes remain connected to the audiovisual
context instead of becoming a separate discussion.

Teachers can review annotations by learner and use an aggregate timeline to identify the parts of the video that
generated the most observations.

## Video sources

- protected Moodle upload;
- direct video or HLS URL;
- YouTube;
- Vimeo.

## Tracking and completion

The activity tracks watched segments, stores the resume position and can use watched percentage and required prompts as
completion criteria. It also preserves annotation data through Moodle backup and restore and exposes the stored learner
data through the Privacy API.
