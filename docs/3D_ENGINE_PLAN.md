# 3D Engine Plan — Netrunner: Uprising & Beyond

> Dieses Dokument beschreibt die 3D-Engine-Erweiterungen nach Code Tycoon.
> Voraussetzung: Code Tycoon ist spielbar (Phase 4 abgeschlossen).
> Referenz: CLAUDE.md fuer Projekt-Kontext und Konventionen.

---

## Ziel

Die VISU-Engine von einer 2D-faehigen zu einer vollwertigen 3D-Engine erweitern.
Erstes 3D-Spiel: **Netrunner: Uprising** (Cyberpunk RPG).

---

## IST-Zustand (was fuer 3D bereits existiert)

| Modul | Status | Details |
|-------|--------|---------|
| OpenGL 4.1 | Vorhanden | php-glfw Bindings, alle Shader-Stages |
| Deferred Rendering | Vorhanden | GBuffer Pass, Deferred Light Pass |
| SSAO | Vorhanden | 3 Qualitaetsstufen (Low/Medium/High) |
| Perspective Camera | Vorhanden | VISUCameraSystem mit Flying-Mode |
| Heightmap Terrain | Vorhanden | CPU + GPU Renderer, Ray-Terrain Intersection |
| Low-Poly Renderer | Vorhanden | OBJ-Modelle, Shadow Casting |
| 3D Debug | Vorhanden | BoundingBox, Ray Visualisierung |
| Transform | Vorhanden | Vollstaendige 3D Transform-Hierarchie |
| Directional Light | Vorhanden | Component mit Direction, Color, Intensity |

---

## Phase 6 — 3D Rendering Pipeline (Wochen 23-28)

**Ziel:** Professionelle 3D-Rendering-Qualitaet.

```
Mesh-System:
[ ] MeshComponent + MeshRenderer System
[ ] glTF 2.0 Loader (Szenen, Meshes, Materialien, Animationen)
    - glTF Binary (.glb) fuer optimierte Ladezeiten
[ ] OBJ Loader verbessern (Normals, UVs, Multi-Material)
[ ] Mesh-Instancing (GPU Instanced Rendering fuer wiederholte Objekte)
[ ] LOD-System (Level of Detail — Mesh-Wechsel nach Kamera-Distanz)

Material-System:
[ ] MaterialComponent (Shader-Referenz + Property-Map)
[ ] PBR Material (Albedo, Normal, Metallic, Roughness, AO Maps)
[ ] Material-Instanzen (Shared Material + Per-Entity Overrides)
[ ] Texture-Atlas / Texture-Arrays fuer Batching
[ ] Shader-Varianten (#define basiert, z.B. HAS_NORMAL_MAP, SKINNED)

Beleuchtung:
[ ] PointLight Component (Position, Color, Intensity, Range, Attenuation)
[ ] SpotLight Component (Direction, Angle, Falloff)
[ ] Shadow Mapping (Directional: Cascaded Shadow Maps, Point: Cubemap Shadows)
[ ] Light Culling (Tile-based oder Clustered, fuer viele Lichter)
[ ] Emissive Materials (Self-Illumination)
[ ] Environment Mapping (Cubemap Reflections, IBL)

Post-Processing:
[ ] Bloom (HDR Glow)
[ ] Tone Mapping (ACES, Reinhard, Filmic)
[ ] Anti-Aliasing (FXAA oder TAA)
[ ] Motion Blur (per-Object oder Screen-Space)
[ ] Depth of Field
[ ] Color Grading / LUT
[ ] Fog (Linear, Exponential, Volumetric)
[ ] Vignette

[ ] MEILENSTEIN: PBR-Material Szene mit mehreren Lichtquellen, Schatten,
    Bloom und SSAO rendert bei 60 FPS.
```

---

## Phase 7 — 3D Physics & Animation (Wochen 29-34)

**Ziel:** Physik-Simulation und skelettale Animation.

```
Physics:
[ ] RigidBody3D Component (Mass, Velocity, Angular Velocity, Drag)
[ ] Collider3D Components (BoxCollider3D, SphereCollider3D, CapsuleCollider3D)
[ ] PhysicsSystem (Broad Phase: AABB-Tree, Narrow Phase: GJK/SAT)
[ ] Collision Response (Impulse-based, Restitution, Friction)
[ ] Trigger Volumes (OnTriggerEnter/Stay/Exit)
[ ] Raycasting 3D (Ray vs. Collider, Layer-basierte Filterung)
[ ] Physics Layers (Collision Matrix: welche Layer kollidieren)
[ ] CharacterController3D (Gravity, Ground Check, Slope Handling, Steps)
[ ] Option: php-bullet oder php-physics C-Extension fuer Performance

Skelettale Animation:
[ ] Skeleton Component (Bone-Hierarchie, Bind Pose)
[ ] SkinnedMeshRenderer (GPU Skinning, Bone Matrices als Uniform Buffer)
[ ] AnimationClip (Keyframes fuer Position/Rotation/Scale pro Bone)
[ ] Animator Component + AnimatorSystem
    - Animation State Machine (States, Transitions, Conditions)
    - Blend Trees (1D/2D Blending zwischen Clips)
    - Animation Layers (Full Body + Upper Body Override)
[ ] glTF Animation Import (Clips aus glTF extrahieren)
[ ] IK System (Inverse Kinematics — Feet Placement, Look-At)
[ ] Root Motion (Bewegung aus Animation statt aus Code)

Partikel-System:
[ ] ParticleEmitter Component
    - Emitter-Formen: Point, Sphere, Box, Cone, Mesh Surface
    - Spawn Rate, Burst, Lifetime
[ ] ParticleSystem (GPU-basiert oder CPU mit Instancing)
    - Size/Color/Velocity over Lifetime (Curves)
    - Gravity, Drag, Turbulence
    - Texture Sheet Animation
    - Sub-Emitters (Partikel spawnen Partikel)
    - Collision mit Welt (optional)
[ ] Vorgefertigte Effekte: Feuer, Rauch, Funken, Regen, Staub

[ ] MEILENSTEIN: Animierter Charakter laeuft durch Szene, kollidiert mit Waenden,
    Partikeleffekte bei Interaktionen.
```

---

## Phase 8 — Netrunner Game Systems (Wochen 35-44)

**Ziel:** Netrunner-spezifische Gameplay-Systeme.

```
Welt & Navigation:
[ ] Navmesh System (Navmesh-Generierung aus Level-Geometrie)
[ ] NavAgent Component (Pathfinding auf Navmesh, Obstacle Avoidance)
[ ] Alternativ: Grid-basiertes A* Pathfinding (einfacher, schneller)
[ ] Waypoint-System (vordefinierte Pfade fuer NPCs)

Charakter-System:
[ ] PlayerController3D (First/Third Person, Mouselook, Springen, Crouchen)
[ ] RPG Stats Component (Hacking, Stealth, Combat, Charisma — Enum-basiert)
[ ] InventorySystem + InventoryComponent (Items, Equipment Slots, Stacking)
[ ] ItemDatabase (JSON-definiert: ID, Name, Typ, Stats, Icon, Beschreibung)

Kampf-System:
[ ] CombatSystem (Echtzeit oder Pausierbar)
[ ] WeaponComponent (Damage, Range, Fire Rate, Ammo, Reload)
[ ] HealthSystem + HealthComponent (HP, Shields, Damage Types, Death)
[ ] HitDetection (Hitscan Raycast oder Projectile Entities)
[ ] AI Combat (Deckung suchen, Flanken, Retreat bei Low HP)

Dialog-System:
[ ] DialogueTree (JSON-definiert, Knoten mit Text + Optionen + Bedingungen)
[ ] DialogueSystem (Baum traversieren, Bedingungen pruefen, Konsequenzen)
[ ] DialogueUI (Portrait, Text mit Typewriter-Effekt, Antwort-Optionen)
[ ] Conditions: Skill-Checks, Quest-State, Inventar, Reputation
[ ] Consequences: Quest starten, Item geben, Reputation aendern

Quest-System:
[ ] QuestSystem + QuestComponent
    - Quest-Definitionen als JSON (Titel, Beschreibung, Objectives, Rewards)
    - Objective-Typen: Kill, Collect, GoTo, Talk, Hack, Deliver
    - Quest-States: Available, Active, Completed, Failed
    - Quest-Tracking UI (aktive Quests, Objectives, Fortschritt)
[ ] QuestTrigger Component (Bereich betreten -> Quest starten/updaten)

Hacking-Minispiel:
[ ] HackingSystem (Netrunner-Kernmechanik)
    - Netzwerk als Graph-Datenstruktur (Nodes + Edges)
    - Node-Typen: Firewall, Data Store, Security, ICE, Access Point
    - Hacking-Actions: Breach, Decrypt, Upload, Download, Disable
    - Zeitdruck: Trace-Timer, Alarm-Level
[ ] HackingUI (Netzwerk-Visualisierung, Node-Details, Action-Auswahl)

KI & Verhalten:
[ ] BehaviorTree System (JSON-definierte Baeume)
    - Node-Typen: Sequence, Selector, Parallel, Decorator, Leaf
    - Leaf Actions: MoveTo, Attack, Patrol, Flee, Idle, Investigate
    - Conditions: CanSeePlayer, IsHealthLow, IsInRange, HasAmmo
[ ] AI Perception (Sichtfeld, Hoerweite, Alarm-Propagation)
[ ] NPC Schedules (Tagesablauf, Routen, Aktivitaeten)

[ ] MEILENSTEIN: Spieler bewegt sich durch Cyberpunk-Level, spricht mit NPCs,
    hackt Terminals, kaempft gegen Feinde. 20+ Minuten Gameplay.
```

---

## Phase 9 — 3D Editor & Tools (Wochen 45-52)

**Ziel:** Visueller 3D-Editor fuer Level-Design.

```
Editor-Erweiterungen:
[ ] 3D Viewport im Vue SPA (WebGL Preview oder Screenshot-Stream)
[ ] Transform Gizmos (Translate/Rotate/Scale — existiert teilweise in GizmoEditorSystem)
[ ] Multi-Select + Group Operations
[ ] Undo/Redo (Command Pattern)
[ ] Copy/Paste Entities
[ ] Snap-to-Grid, Snap-to-Surface

Level-Design Tools:
[ ] Brush-basiertes Level-Building (CSG oder Prefab-Platzierung)
[ ] ProBuilder-aehnliches Mesh-Editing (einfache Geometrie im Editor)
[ ] Material-Zuweisung per Drag & Drop
[ ] Lightmap Baking (optional, Pre-computed GI)

Spezial-Editoren:
[ ] Dialog-Editor (visueller Knoten-Editor fuer Dialogue Trees)
[ ] Behavior-Tree-Editor (visueller Knoten-Editor)
[ ] Quest-Editor (Objectives verketten, Bedingungen konfigurieren)
[ ] Hacking-Level-Editor (Netzwerk-Graph visuell erstellen)

Asset-Pipeline:
[ ] glTF Import mit Preview
[ ] Texture Compression (ETC2, S3TC — je nach Plattform)
[ ] Audio Conversion Pipeline (WAV -> OGG, Normalisierung)
[ ] Asset-Referenz-Tracker (wo wird welches Asset genutzt?)

[ ] MEILENSTEIN: Komplettes Level in Vue Editor gebaut, mit Dialogbaum,
    NPC-Platzierung, Licht-Setup — als JSON exportiert und spielbar.
```

---

## Phase 10 — Polish & Distribution (Wochen 53+)

**Ziel:** Netrunner ist als Standalone-Spiel verteilbar.

```
Performance:
[ ] Frustum Culling (existiert teilweise in src/Geo/Frustum.php)
[ ] Occlusion Culling (optional, fuer dichte Indoor-Level)
[ ] Draw Call Batching (Static/Dynamic Batching)
[ ] Texture Streaming (Mipmap-Level basierend auf Distanz)
[ ] Object Pooling (Entity Recycling fuer Projektile, Partikel, etc.)
[ ] GPU Profiler Dashboard (existiert in src/Instrument/)

Audio 3D:
[ ] Spatial Audio (Listener Position, Panning, Distance Attenuation)
[ ] Audio Occlusion (Waende daempfen Sound)
[ ] Reverb Zones (Halleffekte je nach Raum)
[ ] Music System (Adaptive Music, Layer-basiert je nach Spielzustand)

Cinematics:
[ ] Timeline/Sequencer (Kamera-Fahrten, Animationen, Audio, Events)
[ ] Cutscene-System (Timeline + Dialog + Kamera synchronisiert)
[ ] Camera Tracks (Spline-basierte Kamera-Pfade)

Distribution:
[ ] Build-Script fuer 3D (groessere Assets, optimierte Shader)
[ ] Asset-Bundles (Lazy-Load Level-Assets)
[ ] Mod-Support (Custom Levels, Custom Dialoge, Custom Items)
[ ] Steam-Integration (optional: Achievements, Workshop)

[ ] MEILENSTEIN: Netrunner als Standalone-App verteilbar,
    60+ Minuten Content, stabile Performance.
```

---

## Architektur-Notizen

### Rendering-Architektur (erweitert)

```
Forward+ oder Deferred (existiert bereits) -> PBR Pipeline
                                           -> Shadow Atlas
                                           -> Post-Processing Stack
                                           -> Debug Overlays

Pass-Reihenfolge (3D):
1. Shadow Map Pass (pro Lichtquelle)
2. GBuffer Pass (Geometrie -> Position/Normal/Albedo/Roughness/Metallic)
3. SSAO Pass (existiert)
4. Deferred Light Pass (existiert, erweitern um Point/Spot)
5. Forward Pass (Transparente Objekte, Partikel)
6. Post-Processing Stack (Bloom, Tonemap, AA, DoF, Fog)
7. UI Pass (FlyUI/UIInterpreter)
8. Debug Overlay Pass
```

### Physics-Architektur

```
PhysicsWorld (Singleton Component)
  -> Broad Phase: Dynamic AABB Tree (src/System/VISUAABBTreeSystem.php als Basis)
  -> Narrow Phase: GJK + EPA oder SAT
  -> Solver: Sequential Impulse
  -> Queries: Raycast, Overlap, Sweep

Spaeter optional: php-bullet FFI Bindings fuer native Bullet Physics Performance
```

### AI-Architektur

```
BehaviorTreeSystem
  -> BehaviorTree (JSON -> Baum-Struktur)
     -> Composite: Sequence, Selector, Parallel
     -> Decorator: Inverter, Repeater, Succeeder, UntilFail
     -> Leaf: MoveTo, Attack, Wait, PlayAnimation, SetVariable
  -> Blackboard (Key-Value Store pro Entity fuer AI-State)

PerceptionSystem
  -> SightPerception (Sichtfeld-Kegel, Raycast Sichtpruefung)
  -> HearingPerception (Sound-Events mit Radius)
  -> AlertSystem (Alarm-Propagation zwischen NPCs)
```

---

## Abhaengigkeiten

```
Phase 6 (3D Rendering)   -> Keine (baut auf existierender Pipeline auf)
Phase 7 (Physics & Anim) -> Phase 6 (Meshes + Materials noetig)
Phase 8 (Game Systems)   -> Phase 7 (Physics + Animation noetig)
Phase 9 (3D Editor)      -> Phase 6 (3D Viewport noetig)
Phase 10 (Polish)        -> Phase 8 (Gameplay muss stehen)
```
