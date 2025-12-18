import { ref } from "vue";
import axios from "axios";
import type { AxiosError } from "axios";

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  error?: boolean;
  error_message?: string;
  error_code?: string;
}

export interface IndividualResource {
  id: number;
  name: string;
  description: string | null;
  resource_type: string;
  unit: string;
  price_per_unit: number;
  final_price_per_unit: number;
  discount_applied: number;
  minimum_amount: number;
  maximum_amount: number | null;
}

export interface ResourcePrices {
  resources: IndividualResource[];
  enabled: boolean;
}

export interface PurchaseResourceResponse {
  resource_type: string;
  amount: number;
  price_paid: number;
  price_per_unit: number;
  credits_remaining: number;
}

export function useIndividualResourcesAPI() {
  const loading = ref(false);
  const error = ref<string | null>(null);

  const getResourcePrices = async (): Promise<ResourcePrices> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get<ApiResponse<ResourcePrices>>(
        "/api/user/billingresourcesstore/individual-resources"
      );
      if (response.data.success && response.data.data) {
        return response.data.data;
      }
      throw new Error(response.data.error_message || "Failed to fetch resource prices");
    } catch (err) {
      const axiosError = err as AxiosError<ApiResponse<unknown>>;
      error.value = axiosError.response?.data?.error_message || axiosError.message || "Failed to fetch resource prices";
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const purchaseResources = async (resourceId: number, amount: number): Promise<PurchaseResourceResponse> => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.post<ApiResponse<PurchaseResourceResponse>>(
        "/api/user/billingresourcesstore/individual-resources/purchase",
        { resource_id: resourceId, amount }
      );
      if (response.data.success && response.data.data) {
        return response.data.data;
      }
      throw new Error(response.data.error_message || "Failed to purchase resources");
    } catch (err) {
      const axiosError = err as AxiosError<ApiResponse<unknown>>;
      error.value = axiosError.response?.data?.error_message || axiosError.message || "Failed to purchase resources";
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    getResourcePrices,
    purchaseResources,
  };
}

