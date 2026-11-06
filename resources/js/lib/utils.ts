import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/**
 * Type that converts a registry of classes to a registry of instances.
 * { advisor: typeof AdvisorService } -> { advisor: AdvisorService }
 */
type InstanceRegistry<T> = {
  [K in keyof T]: T[K] extends new (...args: any[]) => infer I ? I : never;
};


/**
 * Creates a type-safe proxy directly from a registry object.
 * Types are inferred automatically - no need for a separate interface.
 *
 * Example:
 *   const registry = { advisor: AdvisorService, branch: BranchService } as const;
 *   const api = createTypedFactoryProxy(registry);
 *   api.advisor.all(); // Fully typed!
 */
export function createTypedFactoryProxy<T extends Record<string, new (...args: any[]) => any>>(
  registry: T,
): InstanceRegistry<T> {
  // Cache to store instantiated service instances
  const instanceCache = new Map<keyof T, InstanceType<T[keyof T]>>();

  return new Proxy({} as InstanceRegistry<T>, {
    get(_target, prop: string | symbol) {
      const key = prop as keyof T;

      if (!(key in registry)) {
        throw new Error(`Service not found for key: ${String(key)}`);
      }

      if (!instanceCache.has(key)) {
        const ServiceClass = registry[key];
        instanceCache.set(key, new ServiceClass());
      }

      return instanceCache.get(key);
    },
  });
}

