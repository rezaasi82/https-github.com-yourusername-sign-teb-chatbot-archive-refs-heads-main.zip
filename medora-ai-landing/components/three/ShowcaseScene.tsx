"use client";

import { Canvas, useFrame } from "@react-three/fiber";
import { Float, Sparkles } from "@react-three/drei";
import { Suspense, useRef } from "react";
import * as THREE from "three";

type ShowcaseSceneProps = {
  /** Scroll progress of the section in [0, 1], driven by the parent. */
  progress: number;
};

function HelixModel({ progress }: { progress: number }) {
  const groupRef = useRef<THREE.Group>(null);
  const knotRef = useRef<THREE.Mesh>(null);
  const coreRef = useRef<THREE.Mesh>(null);

  useFrame(({ clock, pointer, camera }) => {
    const t = clock.getElapsedTime();
    const group = groupRef.current;
    if (group) {
      // scroll drives the master choreography; mouse adds fine-grained tilt
      const targetY = progress * Math.PI * 2.5 + pointer.x * 0.9;
      const targetX = (progress - 0.5) * 0.7 + pointer.y * 0.5;
      group.rotation.y += (targetY - group.rotation.y) * 0.06;
      group.rotation.x += (targetX - group.rotation.x) * 0.06;
      // the model rises slightly as the section scrolls through
      const targetPosY = (progress - 0.5) * -0.9;
      group.position.y += (targetPosY - group.position.y) * 0.05;
    }
    // camera dolly: pushes in toward the core mid-section, pulls back out
    const targetZ = 7.4 - Math.sin(progress * Math.PI) * 2.1;
    camera.position.z += (targetZ - camera.position.z) * 0.05;
    camera.position.x += (pointer.x * 0.5 - camera.position.x) * 0.04;
    camera.lookAt(0, 0, 0);

    if (knotRef.current) {
      knotRef.current.rotation.z = t * 0.15;
    }
    if (coreRef.current) {
      // energy core breathes, and charges up with scroll progress
      const pulse = 0.45 + Math.sin(t * 2.2) * 0.04 + progress * 0.16;
      coreRef.current.scale.setScalar(pulse);
      const mat = coreRef.current.material as THREE.MeshStandardMaterial;
      mat.emissiveIntensity = 1.8 + Math.sin(t * 2.2) * 0.5 + progress * 1.4;
    }
  });

  return (
    <group ref={groupRef}>
      <Float speed={1.2} rotationIntensity={0.25} floatIntensity={0.8}>
        {/* glass torus knot — the "neural helix" */}
        <mesh ref={knotRef}>
          <torusKnotGeometry args={[1.15, 0.34, 220, 40]} />
          <meshPhysicalMaterial
            color="#3b82f6"
            roughness={0.08}
            metalness={0.2}
            transmission={0.72}
            thickness={1.2}
            ior={1.4}
            clearcoat={1}
            clearcoatRoughness={0.08}
            emissive="#1e3a8a"
            emissiveIntensity={0.25}
            envMapIntensity={1.5}
          />
        </mesh>
        {/* inner energy core */}
        <mesh ref={coreRef} scale={0.45}>
          <icosahedronGeometry args={[1, 12]} />
          <meshStandardMaterial
            color="#22d3ee"
            emissive="#06b6d4"
            emissiveIntensity={2.2}
            roughness={0.3}
          />
        </mesh>
      </Float>
      {/* reflective floor disc */}
      <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, -2.1, 0]}>
        <circleGeometry args={[4.5, 64]} />
        <meshStandardMaterial
          color="#0a1030"
          roughness={0.15}
          metalness={0.9}
          transparent
          opacity={0.55}
        />
      </mesh>
      <Sparkles
        count={60}
        scale={8}
        size={1.8}
        speed={0.25}
        opacity={0.4}
        color="#8b5cf6"
      />
    </group>
  );
}

/** Interactive 3D showcase: scroll-rotated glass helix with dynamic lighting. */
export default function ShowcaseScene({ progress }: ShowcaseSceneProps) {
  return (
    <Canvas
      camera={{ position: [0, 0.4, 7.4], fov: 42 }}
      dpr={[1, 1.75]}
      gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
      style={{ background: "transparent" }}
    >
      <ambientLight intensity={0.3} />
      <spotLight
        position={[6, 8, 4]}
        angle={0.5}
        penumbra={1}
        intensity={220}
        color="#93c5fd"
      />
      <pointLight position={[-5, 2, -4]} intensity={40} color="#8b5cf6" />
      <pointLight position={[3, -3, 3]} intensity={25} color="#06b6d4" />
      <Suspense fallback={null}>
        <HelixModel progress={progress} />
      </Suspense>
    </Canvas>
  );
}
