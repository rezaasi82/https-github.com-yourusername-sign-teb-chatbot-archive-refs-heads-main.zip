"use client";

import { Canvas, useFrame } from "@react-three/fiber";
import { Float, MeshDistortMaterial, Sparkles } from "@react-three/drei";
import { Suspense, useRef } from "react";
import * as THREE from "three";

function OrbCore() {
  const meshRef = useRef<THREE.Mesh>(null);
  const lightRef = useRef<THREE.PointLight>(null);

  useFrame(({ clock, pointer }) => {
    const t = clock.getElapsedTime();
    const mesh = meshRef.current;
    if (mesh) {
      // Ease toward the cursor for a soft parallax follow
      mesh.rotation.y += (pointer.x * 0.6 - mesh.rotation.y) * 0.04;
      mesh.rotation.x += (-pointer.y * 0.5 - mesh.rotation.x) * 0.04;
      const s = 1 + Math.sin(t * 1.4) * 0.025;
      mesh.scale.setScalar(s);
    }
    if (lightRef.current) {
      lightRef.current.position.x = Math.sin(t * 0.7) * 3;
      lightRef.current.position.y = Math.cos(t * 0.5) * 2.5;
      lightRef.current.intensity = 18 + Math.sin(t * 2) * 6;
    }
  });

  return (
    <group>
      <pointLight ref={lightRef} color="#22d3ee" intensity={18} distance={12} />
      <Float speed={1.6} rotationIntensity={0.4} floatIntensity={1.2}>
        <mesh ref={meshRef}>
          <icosahedronGeometry args={[1.35, 48]} />
          <MeshDistortMaterial
            color="#3b82f6"
            emissive="#1d4ed8"
            emissiveIntensity={0.35}
            roughness={0.12}
            metalness={0.85}
            distort={0.38}
            speed={2.2}
          />
        </mesh>
        {/* halo shell */}
        <mesh scale={1.25}>
          <icosahedronGeometry args={[1.35, 24]} />
          <meshBasicMaterial
            color="#8b5cf6"
            transparent
            opacity={0.05}
            wireframe
          />
        </mesh>
      </Float>
      {/* orbit rings */}
      <group rotation={[Math.PI / 3.2, 0, 0]}>
        <mesh>
          <torusGeometry args={[2.15, 0.012, 16, 128]} />
          <meshBasicMaterial color="#22d3ee" transparent opacity={0.35} />
        </mesh>
      </group>
      <group rotation={[Math.PI / 2.4, Math.PI / 5, 0]}>
        <mesh>
          <torusGeometry args={[2.55, 0.008, 16, 128]} />
          <meshBasicMaterial color="#8b5cf6" transparent opacity={0.25} />
        </mesh>
      </group>
      <Sparkles
        count={90}
        scale={7}
        size={2.2}
        speed={0.35}
        opacity={0.55}
        color="#93c5fd"
      />
    </group>
  );
}

/** Floating AI orb rendered in its own transparent WebGL canvas. */
export default function AIOrb() {
  return (
    <Canvas
      camera={{ position: [0, 0, 5.5], fov: 45 }}
      dpr={[1, 1.75]}
      gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
      style={{ background: "transparent" }}
    >
      <ambientLight intensity={0.35} />
      <directionalLight position={[4, 6, 4]} intensity={1.4} color="#93c5fd" />
      <pointLight position={[-4, -2, -3]} intensity={10} color="#8b5cf6" />
      <Suspense fallback={null}>
        <OrbCore />
      </Suspense>
    </Canvas>
  );
}
