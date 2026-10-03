<!-- Upload Modal -->
<div id="uploadModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
    <div id="modalContainer" style="background: white; border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); transform: scale(0.9) translateY(20px); transition: all 0.3s ease; font-family: 'Poppins', sans-serif;">
    
    <!-- Modal Header -->
    <div style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 24px; border-radius: 16px 16px 0 0; position: relative; overflow: hidden; font-family: 'Poppins', sans-serif;">
        <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 80px; height: 80px; background: rgba(255, 255, 255, 0.1); border-radius: 50%;"></div>
        
        <div style="display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 1; font-family: 'Poppins', sans-serif;">
            <div style="font-family: 'Poppins', sans-serif;">
                <h2 style="margin: 0; font-size: 24px; font-weight: 600; margin-bottom: 4px; font-family: 'Poppins', sans-serif;">
                    <i class='bx bxs-cloud-upload' style="margin-right: 8px; font-size: 28px;"></i>
                    Upload Files
                </h2>
                <p style="margin: 0; opacity: 0.9; font-size: 14px; font-family: 'Poppins', sans-serif;">Share your documents with the department</p>
            </div>
            <button onclick="closeUploadModal()" style="background: rgba(255, 255, 255, 0.2); border: none; border-radius: 8px; padding: 8px; color: white; cursor: pointer; transition: all 0.3s ease; font-size: 20px; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='rgba(255, 255, 255, 0.3)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.2)'">
                <i class='bx bx-x'></i>
            </button>
        </div>
    </div>

    <!-- Modal Content -->
    <form id="uploadForm" style="padding: 0; font-family: 'Poppins', sans-serif;">
        
        <!-- Department Selection -->
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; font-family: 'Poppins', sans-serif;">
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px; font-family: 'Poppins', sans-serif;">
                <i class='bx bxs-building' style="color: #10b981; margin-right: 8px;"></i>
                Select Department
            </label>
            <select id="department" required style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; background: white; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'">
                <option value="">Choose a department...</option>
                <?php foreach ($departments as $code => $dept): ?>
                <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($dept['name']) . ' (' . $code . ')'; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Semester Selection -->
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; font-family: 'Poppins', sans-serif;">
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px; font-family: 'Poppins', sans-serif;">
                <i class='bx bxs-calendar' style="color: #10b981; margin-right: 8px;"></i>
                Academic Semester
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-family: 'Poppins', sans-serif;">
                <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;" onmouseover="this.style.borderColor='#10b981'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">
                    <input type="radio" name="semester" value="first" required style="margin-right: 12px; transform: scale(1.2); accent-color: #10b981;">
                    <div style="font-family: 'Poppins', sans-serif;">
                        <div style="font-weight: 600; color: #374151; font-family: 'Poppins', sans-serif;">First Semester</div>
                        <div style="font-size: 12px; color: #6b7280; font-family: 'Poppins', sans-serif;">August - December</div>
                    </div>
                </label>
                <label style="display: flex; align-items: center; padding: 16px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; background: white; font-family: 'Poppins', sans-serif;" onmouseover="this.style.borderColor='#10b981'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='white'">
                    <input type="radio" name="semester" value="second" required style="margin-right: 12px; transform: scale(1.2); accent-color: #10b981;">
                    <div style="font-family: 'Poppins', sans-serif;">
                        <div style="font-weight: 600; color: #374151; font-family: 'Poppins', sans-serif;">Second Semester</div>
                        <div style="font-size: 12px; color: #6b7280; font-family: 'Poppins', sans-serif;">January - May</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- File Upload Area -->
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; font-family: 'Poppins', sans-serif;">
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px; font-family: 'Poppins', sans-serif;">
                <i class='bx bxs-file-plus' style="color: #10b981; margin-right: 8px;"></i>
                Upload Files
            </label>
            
            <div id="dropZone" style="border: 2px dashed #10b981; border-radius: 12px; padding: 40px 20px; text-align: center; background: linear-gradient(135deg, #f0fdf4, #ecfdf5); cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden; font-family: 'Poppins', sans-serif;" onclick="document.getElementById('fileInput').click()" ondragover="event.preventDefault(); this.style.borderColor='#059669'; this.style.background='#d1fae5';" ondragleave="this.style.borderColor='#10b981'; this.style.background='linear-gradient(135deg, #f0fdf4, #ecfdf5)';" ondrop="handleDrop(event)">
                <input type="file" id="fileInput" multiple accept="*" style="display: none;" onchange="handleFileSelect(this.files)">
                
                <div id="uploadPrompt" style="font-family: 'Poppins', sans-serif;">
                    <i class='bx bxs-cloud-upload' style="font-size: 48px; color: #10b981; margin-bottom: 16px; display: block;"></i>
                    <h3 style="margin: 0 0 8px 0; color: #065f46; font-size: 18px; font-weight: 600; font-family: 'Poppins', sans-serif;">Drop files here or click to browse</h3>
                    <p style="margin: 0; color: #059669; font-size: 14px; opacity: 0.8; font-family: 'Poppins', sans-serif;">Support for PDF, DOC, XLS, PPT, Images and more</p>
                    <div style="margin-top: 16px; display: inline-flex; align-items: center; background: rgba(16, 185, 129, 0.1); padding: 8px 16px; border-radius: 20px; font-size: 12px; color: #065f46; font-weight: 500; font-family: 'Poppins', sans-serif;">
                        <i class='bx bx-info-circle' style="margin-right: 6px;"></i>
                        Max file size: 50MB per file
                    </div>
                </div>
                
                <div id="filePreview" style="display: none; text-align: left; font-family: 'Poppins', sans-serif;"></div>
            </div>
            
            <!-- Progress Bar -->
            <div id="uploadProgress" style="display: none; margin-top: 16px; font-family: 'Poppins', sans-serif;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-family: 'Poppins', sans-serif;">
                    <span style="font-size: 14px; font-weight: 500; color: #374151; font-family: 'Poppins', sans-serif;">Uploading files...</span>
                    <span id="progressPercent" style="font-size: 14px; font-weight: 500; color: #10b981; font-family: 'Poppins', sans-serif;">0%</span>
                </div>
                <div style="width: 100%; background: #f3f4f6; border-radius: 8px; height: 8px; overflow: hidden;">
                    <div id="progressBar" style="width: 0%; background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 8px; transition: width 0.3s ease;"></div>
                </div>
            </div>
        </div>

        <!-- File Description -->
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; font-family: 'Poppins', sans-serif;">
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px; font-family: 'Poppins', sans-serif;">
                <i class='bx bxs-note' style="color: #10b981; margin-right: 8px;"></i>
                Description (Optional)
            </label>
            <textarea id="fileDescription" placeholder="Add a description for your files..." style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; resize: vertical; min-height: 80px; font-family: 'Poppins', sans-serif; transition: all 0.3s ease;" onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.1)'" onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"></textarea>
        </div>

        <!-- File Tags -->
        <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; font-family: 'Poppins', sans-serif;">
            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 12px; font-size: 16px; font-family: 'Poppins', sans-serif;">
                <i class='bx bxs-tag' style="color: #10b981; margin-right: 8px;"></i>
                Tags
            </label>
            <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; font-family: 'Poppins', sans-serif;">
                <button type="button" onclick="addTag('Curriculum')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Curriculum</button>
                <button type="button" onclick="addTag('Research')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Research</button>
                <button type="button" onclick="addTag('Guidelines')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Guidelines</button>
                <button type="button" onclick="addTag('Reports')" style="background: #f0fdf4; color: #065f46; border: 1px solid #10b981; border-radius: 16px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='#10b981'; this.style.color='white'" onmouseout="this.style.background='#f0fdf4'; this.style.color='#065f46'">Reports</button>
            </div>
            <div id="selectedTags" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; font-family: 'Poppins', sans-serif;"></div>
            <input type="text" id="customTag" placeholder="Add custom tag..." style="width: 100%; padding: 8px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#e5e7eb'" onkeypress="if(event.key==='Enter') {event.preventDefault(); addCustomTag();}">
        </div>

        <!-- Modal Footer -->
        <div style="padding: 24px; background: #f9fafb; border-radius: 0 0 16px 16px; font-family: 'Poppins', sans-serif;">
            <div style="display: flex; gap: 12px; justify-content: flex-end; font-family: 'Poppins', sans-serif;">
                <button type="button" onclick="closeUploadModal()" style="background: #f3f4f6; color: #374151; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'">
                    Cancel
                </button>
                <button type="submit" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; border-radius: 8px; padding: 12px 24px; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; font-family: 'Poppins', sans-serif;" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <i class='bx bxs-cloud-upload' style="margin-right: 8px;"></i>
                    Upload Files
                </button>
            </div>
            
            <div style="margin-top: 16px; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 4px solid #10b981; font-family: 'Poppins', sans-serif;">
                <p style="margin: 0; font-size: 12px; color: #065f46; line-height: 1.4; font-family: 'Poppins', sans-serif;">
                    <i class='bx bx-shield-check' style="margin-right: 4px;"></i>
                    Your files will be securely stored and organized by department and semester for easy access.
                </p>
            </div>
        </div>
    </form>
    </div>
</div>